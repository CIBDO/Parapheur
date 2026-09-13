<?php

namespace App\Services;

use App\Enums\NumberingSequenceCode;
use App\Enums\TransmissionSlipStatus;
use App\Models\TransmissionSlip;
use App\Models\TransmissionSlipItem;
use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TransmissionSlipService
{
    public function __construct(
        protected NumberingService $numberingService,
        protected DocumentTemplateService $templateService
    ) {}

    public function create(array $data, array $items = []): TransmissionSlip
    {
        return DB::transaction(function () use ($data, $items) {
            $slip = TransmissionSlip::create([
                'from_structure_id' => $data['from_structure_id'] ?? null,
                'to_structure_id' => $data['to_structure_id'] ?? null,
                'nature' => $data['nature'] ?? null,
                'observations' => $data['observations'] ?? null,
                'status' => TransmissionSlipStatus::Brouillon->value,
                'created_by' => Auth::id(),
            ]);

            // Add items
            foreach ($items as $index => $itemData) {
                $slip->items()->create([
                    'correspondence_id' => $itemData['correspondence_id'] ?? null,
                    'reference' => $itemData['reference'] ?? null,
                    'object' => $itemData['object'] ?? null,
                    'piece_count' => $itemData['piece_count'] ?? 1,
                    'observations' => $itemData['observations'] ?? null,
                    'display_order' => $index,
                ]);
            }

            return $slip->load(['items.correspondence', 'fromStructure', 'toStructure']);
        });
    }

    public function addItems(TransmissionSlip $slip, array $items): TransmissionSlip
    {
        return DB::transaction(function () use ($slip, $items) {
            $currentMaxOrder = $slip->items()->max('display_order') ?? -1;

            foreach ($items as $index => $itemData) {
                $slip->items()->create([
                    'correspondence_id' => $itemData['correspondence_id'] ?? null,
                    'reference' => $itemData['reference'] ?? null,
                    'object' => $itemData['object'] ?? null,
                    'piece_count' => $itemData['piece_count'] ?? 1,
                    'observations' => $itemData['observations'] ?? null,
                    'display_order' => $currentMaxOrder + $index + 1,
                ]);
            }

            return $slip->fresh(['items.correspondence']);
        });
    }

    public function removeItem(TransmissionSlip $slip, int $itemId): TransmissionSlip
    {
        return DB::transaction(function () use ($slip, $itemId) {
            $slip->items()->where('id', $itemId)->delete();
            
            // Reorder display_order
            $slip->items()->orderBy('display_order')->get()->each(function ($item, $index) {
                $item->display_order = $index;
                $item->save();
            });

            return $slip->fresh(['items']);
        });
    }

    public function validate(TransmissionSlip $slip): TransmissionSlip
    {
        return DB::transaction(function () use ($slip) {
            if (!$slip->number) {
                $slip->number = $this->numberingService->generateTransmissionSlipNumber($slip->from_structure_id);
            }

            $slip->status = TransmissionSlipStatus::Valide->value;
            $slip->validated_by = Auth::id();
            $slip->validated_at = now();
            $slip->save();

            return $slip;
        });
    }

    public function generateDocument(TransmissionSlip $slip): TransmissionSlip
    {
        return DB::transaction(function () use ($slip) {
            // Find bordereau template
            $template = DocumentTemplate::where('kind', 'bordereau_transmission')
                ->where('is_active', true)
                ->whereNotNull('current_published_version_id')
                ->first();

            if (! $slip->number) {
                $slip->number = $this->numberingService->generateTransmissionSlipNumber($slip->from_structure_id);
            }

            if (!$template) {
                // Fallback: créer un document minimal si pas de template
                $renderer = app(DocumentTemplateRenderer::class);
                $blankPath = $renderer->createBlankDocx(
                    'Bordereau de transmission ' . $slip->number,
                    [
                        'Numéro : ' . $slip->number,
                        'Date : ' . now()->format('d/m/Y'),
                        'De : ' . ($slip->fromStructure?->name ?? ''),
                        'À : ' . ($slip->toStructure?->name ?? ''),
                        'Nature : ' . ($slip->nature ?? ''),
                        '',
                        'Documents transmis :',
                    ]
                );

                $upload = new \Illuminate\Http\UploadedFile(
                    $blankPath,
                    'bordereau-' . $slip->number . '.docx',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    null,
                    true
                );

                $document = app(DocumentService::class)->create(Auth::user(), [
                    'origin' => \App\Enums\DocumentOrigin::Courrier->value,
                    'object' => 'Bordereau de transmission ' . $slip->number,
                    'title' => 'Bordereau ' . $slip->number,
                    'reference' => $slip->number,
                ], $upload);

                @unlink($blankPath);

                $slip->document_id = $document->id;
                $slip->status = TransmissionSlipStatus::Genere;
                $slip->save();

                return $slip->fresh(['document']);
            }

            // Prepare variables
            $variables = [
                'numero' => $slip->number,
                'date' => now()->format('d/m/Y'),
                'expediteur' => $slip->fromStructure?->name ?? '',
                'destinataire' => $slip->toStructure?->name ?? '',
                'nature' => $slip->nature ?? '',
                'observations' => $slip->observations ?? '',
                'items' => $slip->items->map(fn($item) => [
                    'reference' => $item->reference ?? $item->correspondence?->arrival_number ?? '',
                    'objet' => $item->object ?? $item->correspondence?->subject ?? '',
                    'pieces' => $item->piece_count ?? 1,
                    'observations' => $item->observations ?? '',
                ])->toArray(),
            ];

            $document = $this->templateService->generateDocument(
                $template,
                Auth::user(),
                $variables,
                transmissionSlipId: $slip->id,
            );

            $slip->document_id = $document->id;
            $slip->template_version_id = $template->current_published_version_id;
            $slip->status = TransmissionSlipStatus::Genere;
            $slip->save();

            return $slip->fresh(['document']);
        });
    }

    public function markPrinted(TransmissionSlip $slip): TransmissionSlip
    {
        if (! $slip->document_id) {
            $this->generateDocument($slip);
            $slip->refresh();
        }

        $slip->printed_at = now();
        $slip->status = TransmissionSlipStatus::Imprime;
        $slip->save();

        return $slip;
    }

    public function send(TransmissionSlip $slip): TransmissionSlip
    {
        return DB::transaction(function () use ($slip) {
            if ($slip->status === TransmissionSlipStatus::Brouillon->value) {
                $this->validate($slip);
            }

            if (!$slip->document_id) {
                $this->generateDocument($slip);
            }

            $slip->status = TransmissionSlipStatus::Transmis->value;
            $slip->transmitted_at = now();
            $slip->save();

            // TODO: Notification au destinataire

            return $slip;
        });
    }

    public function acknowledge(TransmissionSlip $slip, array $data = [], $proofFile = null, $user = null): TransmissionSlip
    {
        return DB::transaction(function () use ($slip, $data, $proofFile, $user) {
            $slip->status = TransmissionSlipStatus::Recu;
            $slip->received_at = $data['received_at'] ?? now();
            $slip->observations = $data['observations'] ?? $slip->observations;
            $slip->save();

            $actor = $user ?? Auth::user();

            if ($proofFile && $actor) {
                if ($slip->document_id) {
                    app(DocumentService::class)->addAttachment(
                        $slip->document,
                        $actor,
                        $proofFile,
                        'justificatif'
                    );
                } else {
                    $typeId = \App\Models\DocumentType::query()->value('id');
                    $document = app(DocumentService::class)->create($actor, [
                        'origin' => 'courrier',
                        'object' => 'Preuve réception '.$slip->number,
                        'title' => 'Preuve réception bordereau',
                        'document_type_id' => $typeId,
                    ], $proofFile);
                    $slip->document_id = $document->id;
                    $slip->save();
                }
            }

            return $slip->fresh(['document']);
        });
    }
}
