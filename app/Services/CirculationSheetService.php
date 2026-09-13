<?php

namespace App\Services;

use App\Enums\NumberingSequenceCode;
use App\Enums\TransmissionSlipStatus;
use App\Models\CirculationSheet;
use App\Models\Correspondence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CirculationSheetService
{
    public function __construct(
        private readonly NumberingService $numberingService,
        private readonly DocumentTemplateService $templateService,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Correspondence $correspondence, User $user, array $data = []): CirculationSheet
    {
        return DB::transaction(function () use ($correspondence, $user, $data) {
            $number = $this->numberingService->nextNumber(
                NumberingSequenceCode::CirculationSheet,
                structureId: $correspondence->structure_id
            );

            $sheet = CirculationSheet::query()->create([
                'correspondence_id' => $correspondence->id,
                'number' => $number,
                'status' => $data['status'] ?? 'en_cours',
                'document_id' => $data['document_id'] ?? null,
                'template_version_id' => $data['template_version_id'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->audit->log('circulation_sheet.created', $sheet, [
                'actor_id' => $user->id,
                'correspondence_id' => $correspondence->id,
            ]);

            return $sheet->fresh(['correspondence', 'document', 'creator']);
        });
    }

    public function list(array $filters = [])
    {
        $query = CirculationSheet::query()->with(['correspondence', 'creator', 'document']);

        if (! empty($filters['correspondence_id'])) {
            $query->where('correspondence_id', $filters['correspondence_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('id')->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Génère le document de la fiche de circulation à partir du template.
     */
    public function generateDocument(CirculationSheet $sheet, User $user): CirculationSheet
    {
        // Charger la correspondance complète
        $correspondence = $sheet->correspondence->loadMissing([
            'parties.correspondent',
            'channel',
            'category',
            'qualification',
            'registeredBy',
            'structure',
        ]);

        $variables = $this->buildVariables($sheet, $correspondence);

        $template = \App\Models\DocumentTemplate::query()
            ->where('kind', 'fiche_circulation')
            ->where('is_active', true)
            ->whereHas('currentPublishedVersion')
            ->first();

        if ($template) {
            $document = $this->templateService->generateDocument(
                template: $template,
                user: $user,
                variables: $variables,
                correspondenceId: $correspondence->id
            );
            $sheet->template_version_id = $template->current_published_version_id;
        } else {
            $renderer = app(DocumentTemplateRenderer::class);
            $path = $renderer->createBlankDocx(
                'Fiche de circulation '.$sheet->number,
                [
                    'Objet : '.($variables['objet'] ?? ''),
                    'N° arrivée : '.($variables['numero_arrivee'] ?? ''),
                    'Expéditeur : '.($variables['expediteur'] ?? ''),
                    'Destinataires : '.($variables['destinataires'] ?? ''),
                    'Priorité : '.($variables['priorite'] ?? ''),
                ]
            );

            $uploaded = new \Illuminate\Http\UploadedFile(
                $path,
                'fiche-circulation.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                null,
                true
            );

            $document = app(DocumentService::class)->create($user, [
                'origin' => 'courrier',
                'object' => 'Fiche circulation '.$sheet->number,
                'title' => 'Fiche de circulation',
                'document_type_id' => \App\Models\DocumentType::query()->value('id'),
            ], $uploaded);

            @unlink($path);
        }

        $sheet->document_id = $document->id;
        $sheet->save();

        $this->audit->log('circulation_sheet.document_generated', $sheet, [
            'actor_id' => $user->id,
            'document_id' => $document->id,
        ]);

        return $sheet->fresh(['document', 'correspondence']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildVariables(CirculationSheet $sheet, Correspondence $correspondence): array
    {
        $fromParty = $correspondence->parties->firstWhere('role', 'from');
        $toParties = $correspondence->parties->where('role', 'to');

        return [
            'numero_fiche' => $sheet->number,
            'numero_arrivee' => $correspondence->arrival_number,
            'numero_depart' => $correspondence->departure_number,
            'date_enregistrement' => $correspondence->registered_at?->format('d/m/Y'),
            'objet' => $correspondence->subject,
            'resume' => $correspondence->summary ?? '',
            'observations' => $correspondence->observations ?? '',
            'reference_externe' => $correspondence->external_reference ?? '',
            'date_correspondance' => $correspondence->correspondence_date?->format('d/m/Y') ?? '',
            'date_reception' => $correspondence->received_at?->format('d/m/Y') ?? '',
            'date_echeance' => $correspondence->due_date?->format('d/m/Y') ?? '',
            'priorite' => $correspondence->priority?->value ?? 'normale',
            'confidentialite' => $correspondence->confidentiality?->value ?? 'normal',
            'nombre_pieces' => $correspondence->piece_count ?? 0,
            'canal' => $correspondence->channel?->name ?? '',
            'categorie' => $correspondence->category?->name ?? '',
            'qualification' => $correspondence->qualification?->name ?? '',
            'structure' => $correspondence->structure?->name ?? '',
            'enregistre_par' => $correspondence->registeredBy?->name ?? '',
            'expediteur' => $fromParty?->name ?? $fromParty?->correspondent?->name ?? '',
            'expediteur_organisation' => $fromParty?->organization ?? $fromParty?->correspondent?->organization ?? '',
            'expediteur_fonction' => $fromParty?->function ?? '',
            'destinataires' => $toParties->map(fn ($party) => $party->name ?? $party->correspondent?->name ?? '')
                ->filter()
                ->implode(', '),
        ];
    }
}
