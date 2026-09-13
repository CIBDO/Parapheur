<?php

namespace App\Services;

use App\Enums\DocumentTemplateKind;
use App\Enums\NumberingSequenceCode;
use App\Models\Correspondence;
use App\Models\CorrespondenceAcknowledgement;
use App\Models\CorrespondenceDispatch;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * États de sortie courrier (même charte que la fiche de circulation) :
 * bordereau d'envoi, accusé de réception.
 */
class MailOutputDocumentService
{
    public function __construct(
        private readonly NumberingService $numberingService,
        private readonly DocumentTemplateService $templateService,
        private readonly DocumentTemplateRenderer $renderer,
        private readonly DocumentService $documentService,
        private readonly AuditLogger $audit,
    ) {}

    public function generateDispatchSlip(CorrespondenceDispatch $dispatch, User $user): CorrespondenceDispatch
    {
        return DB::transaction(function () use ($dispatch, $user) {
            $correspondence = $dispatch->correspondence()->with([
                'parties.correspondent',
                'registeredBy',
                'structure',
            ])->firstOrFail();

            $dispatch->loadMissing('dispatchedBy');

            if (! $dispatch->number) {
                $dispatch->number = $this->numberingService->generateDispatchSlipNumber($correspondence->structure_id);
            }

            $variables = $this->buildDispatchVariables($dispatch, $correspondence);
            $document = $this->createOrRenderDocument(
                kind: DocumentTemplateKind::BordereauEnvoi,
                user: $user,
                variables: $variables,
                correspondence: $correspondence,
                filename: 'bordereau-envoi-'.$dispatch->number.'.docx',
                title: 'Bordereau d\'envoi — '.$dispatch->number,
                object: 'Bordereau d\'envoi '.$dispatch->number,
                reference: $dispatch->number,
                fallback: fn () => $this->renderer->createDispatchSlipDocx($variables),
                existingDocumentId: $dispatch->document_id,
            );

            $dispatch->document_id = $document->id;
            $dispatch->save();

            $this->audit->log('correspondence.dispatch_slip_generated', $dispatch, [
                'actor_id' => $user->id,
                'document_id' => $document->id,
            ]);

            return $dispatch->fresh(['document', 'dispatchedBy']);
        });
    }

    public function generateAcknowledgementDocument(CorrespondenceAcknowledgement $ack, User $user): CorrespondenceAcknowledgement
    {
        return DB::transaction(function () use ($ack, $user) {
            $correspondence = $ack->correspondence()->with([
                'parties.correspondent',
                'registeredBy',
                'structure',
            ])->firstOrFail();

            if (! $ack->number) {
                $ack->number = $this->numberingService->generateAcknowledgementNumber($correspondence->structure_id);
            }

            $variables = $this->buildAcknowledgementVariables($ack, $correspondence);
            $document = $this->createOrRenderDocument(
                kind: DocumentTemplateKind::AccuseReception,
                user: $user,
                variables: $variables,
                correspondence: $correspondence,
                filename: 'accuse-reception-'.$ack->number.'.docx',
                title: 'Accusé de réception — '.$ack->number,
                object: 'Accusé de réception '.$ack->number,
                reference: $ack->number,
                fallback: fn () => $this->renderer->createAcknowledgementDocx($variables),
                existingDocumentId: $ack->document_id,
            );

            $ack->document_id = $document->id;
            $ack->save();

            $this->audit->log('correspondence.acknowledgement_document_generated', $ack, [
                'actor_id' => $user->id,
                'document_id' => $document->id,
            ]);

            return $ack->fresh(['document', 'registeredBy']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDispatchVariables(CorrespondenceDispatch $dispatch, Correspondence $correspondence): array
    {
        $methodLabels = [
            'courrier' => 'Courrier postal',
            'coursier' => 'Coursier',
            'fax' => 'Fax',
            'email' => 'Courriel',
            'plateforme' => 'Plateforme',
        ];

        return [
            'saisi_par' => $dispatch->dispatchedBy?->name ?? $correspondence->registeredBy?->name ?? '',
            'numero_bordereau' => $dispatch->number ?? '',
            'numero_depart' => $correspondence->departure_number ?? $correspondence->arrival_number ?? '',
            'reference_externe' => $correspondence->external_reference ?? '',
            'date_envoi' => optional($dispatch->dispatched_at)->format('d/m/Y') ?? now()->format('d/m/Y'),
            'mode_envoi' => $methodLabels[$dispatch->method] ?? $dispatch->method,
            'numero_suivi' => $dispatch->tracking_number ?? '',
            'expediteur' => $this->partyNames($correspondence, 'from') ?: ($correspondence->structure?->name ?? 'DGTCP'),
            'destinataires' => $this->partyNames($correspondence, 'to') ?: '—',
            'ampliations' => $this->partyNames($correspondence, ['cc', 'ampliation', 'info']),
            'objet' => $correspondence->subject ?? '',
            'nombre_pieces' => (int) ($correspondence->piece_count ?? 0),
            'observations' => $dispatch->observations ?? '',
            'confidentialite_label' => match ($correspondence->confidentiality?->value ?? 'normal') {
                'confidentiel', 'tres_confidentiel' => 'CONFIDENTIEL',
                'restreint' => 'RESTREINT',
                default => 'ORDINAIRE',
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAcknowledgementVariables(CorrespondenceAcknowledgement $ack, Correspondence $correspondence): array
    {
        return [
            'saisi_par' => $ack->registeredBy?->name ?? $correspondence->registeredBy?->name ?? '',
            'numero_accuse' => $ack->number ?? '',
            'numero_enregistrement' => $correspondence->arrival_number
                ?? $correspondence->departure_number
                ?? '',
            'date_accuse' => optional($ack->acknowledged_at)->format('d/m/Y') ?? now()->format('d/m/Y'),
            'date_correspondance' => optional($correspondence->correspondence_date)->format('d/m/Y')
                ?? optional($correspondence->received_at)->format('d/m/Y')
                ?? '',
            'mode_accuse' => $ack->method ?? 'signature',
            'signataire' => $ack->acknowledged_by_name ?? '',
            'expediteur' => $this->partyNames($correspondence, 'from') ?: '—',
            'objet' => $correspondence->subject ?? '',
            'observations' => $ack->observations ?? '',
        ];
    }

    /**
     * @param  callable(): string  $fallback
     */
    private function createOrRenderDocument(
        DocumentTemplateKind $kind,
        User $user,
        array $variables,
        Correspondence $correspondence,
        string $filename,
        string $title,
        string $object,
        string $reference,
        callable $fallback,
        ?int $existingDocumentId = null,
    ): \App\Models\Document {
        try {
            $existing = $this->resolveExistingDocument($existingDocumentId, $reference);

            $template = DocumentTemplate::query()
                ->where('kind', $kind->value)
                ->where('is_active', true)
                ->whereNotNull('current_published_version_id')
                ->first();

            // Régénération : nouvelle version sur le document GED existant (évite le conflit de référence unique)
            if ($existing) {
                $path = $template
                    ? $this->renderPublishedTemplate($template, $variables)
                    : $fallback();

                $uploaded = new UploadedFile(
                    $path,
                    $filename,
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    null,
                    true
                );

                $this->documentService->addVersion($existing, $user, $uploaded, 'Régénération du document');
                $existing->title = $title;
                $existing->object = $object;
                $existing->save();
                @unlink($path);

                return $existing->fresh();
            }

            if ($template) {
                return $this->templateService->generateDocument(
                    template: $template,
                    user: $user,
                    variables: $variables,
                    correspondenceId: $correspondence->id,
                );
            }

            $path = $fallback();
            $uploaded = new UploadedFile(
                $path,
                $filename,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                null,
                true
            );

            $document = $this->documentService->create($user, [
                'origin' => 'courrier',
                'object' => $object,
                'title' => $title,
                'reference' => $reference,
                'document_type_id' => DocumentType::query()->value('id'),
                'structure_id' => $correspondence->structure_id,
                'confidentiality' => $correspondence->confidentiality?->value,
            ], $uploaded);

            @unlink($path);

            return $document;
        } catch (Throwable $e) {
            throw new \InvalidArgumentException(
                'Impossible de générer le document : '.$e->getMessage(),
                previous: $e
            );
        }
    }

    private function renderPublishedTemplate(DocumentTemplate $template, array $variables): string
    {
        $version = $template->currentPublishedVersion;
        if (! $version) {
            throw new \InvalidArgumentException('Aucun modèle publié disponible.');
        }

        $absolute = app(PrivateDocumentStorage::class)->absolutePath($version->disk, $version->path);

        return $this->renderer->render($absolute, $variables);
    }

    private function resolveExistingDocument(?int $existingDocumentId, string $reference): ?\App\Models\Document
    {
        if ($existingDocumentId) {
            $doc = \App\Models\Document::query()->find($existingDocumentId);
            if ($doc) {
                return $doc;
            }
        }

        if ($reference !== '') {
            return \App\Models\Document::query()->where('reference', $reference)->first();
        }

        return null;
    }

    /**
     * @param  string|list<string>  $roles
     */
    private function partyNames(Correspondence $correspondence, string|array $roles): string
    {
        $wanted = is_array($roles) ? $roles : [$roles];

        $names = $correspondence->parties
            ->filter(function ($party) use ($wanted) {
                $role = $party->role instanceof \BackedEnum ? $party->role->value : (string) $party->role;

                return in_array($role, $wanted, true);
            })
            ->map(function ($party) {
                return trim((string) ($party->name
                    ?? $party->correspondent?->name
                    ?? $party->organization
                    ?? $party->correspondent?->organization
                    ?? ''));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return implode(', ', $names);
    }
}
