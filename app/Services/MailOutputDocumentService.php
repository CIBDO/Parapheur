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
        private readonly MailSheetPdfService $sheets,
        private readonly MailIdentityService $identity,
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

            $dispatch = $this->ensureSlipContent($dispatch, $correspondence);
            $path = $this->sheets->writeDispatchSlip($this->pdfPayload($dispatch, $correspondence));
            $uploaded = new UploadedFile(
                $path,
                MailSheetPdfService::safeFilename('bordereau-envoi-'.$dispatch->number.'.pdf'),
                'application/pdf',
                null,
                true
            );

            if ($dispatch->document_id && ($existing = \App\Models\Document::query()->find($dispatch->document_id))) {
                $this->documentService->addVersion($existing, $user, $uploaded, 'Régénération bordereau d\'envoi');
                $existing->title = 'Bordereau d\'envoi — '.$dispatch->number;
                $existing->object = 'Bordereau d\'envoi '.$dispatch->number;
                $existing->save();
                $document = $existing->fresh();
            } elseif ($dispatch->number && ($existing = \App\Models\Document::query()->where('reference', $dispatch->number)->first())) {
                $this->documentService->addVersion($existing, $user, $uploaded, 'Régénération bordereau d\'envoi');
                $document = $existing->fresh();
            } else {
                $document = $this->documentService->create($user, [
                    'origin' => 'courrier',
                    'object' => 'Bordereau d\'envoi '.$dispatch->number,
                    'title' => 'Bordereau d\'envoi — '.$dispatch->number,
                    'reference' => $dispatch->number,
                    'document_type_id' => DocumentType::query()->value('id'),
                    'structure_id' => $correspondence->structure_id,
                    'confidentiality' => $correspondence->confidentiality?->value,
                ], $uploaded);
            }

            @unlink($path);

            $dispatch->document_id = $document->id;
            $dispatch->save();

            $this->audit->log('correspondence.dispatch_slip_generated', $dispatch, [
                'actor_id' => $user->id,
                'document_id' => $document->id,
            ]);

            return $dispatch->fresh(['document', 'dispatchedBy']);
        });
    }

    /**
     * Valeurs proposées pour le bordereau, toutes remplaçables à la saisie.
     *
     * @return array{presentation: array{place: string, sender_title: string, recipient_title: string, recipient_city: string, structure_code: string}, lines: list<array{designation: string, quantity: int, observation: string}>}
     */
    public function suggestSlip(Correspondence $correspondence): array
    {
        $correspondence->loadMissing(['parties.correspondent', 'structure']);
        $identity = $this->identity->forCorrespondence($correspondence);

        $recipient = $this->partyNames($correspondence, 'to');
        $toParty = $correspondence->parties->first(function ($party) {
            $role = $party->role instanceof \BackedEnum ? $party->role->value : (string) $party->role;

            return $role === 'to';
        });
        $organization = trim((string) ($toParty?->organization ?: $toParty?->correspondent?->organization ?: ''));
        $city = $organization !== '' && mb_strtolower($organization) !== mb_strtolower($recipient) ? $organization : '';

        $sender = trim((string) ($identity['signatory_left'] ?? ''));
        if ($sender === '' || $sender === '—') {
            $sender = $correspondence->structure?->name ?? '';
        }

        return [
            'presentation' => [
                'place' => 'Bamako',
                'sender_title' => $sender,
                'recipient_title' => $recipient,
                'recipient_city' => $city,
                'structure_code' => (string) ($identity['structure_code'] ?? $correspondence->structure?->code ?? ''),
            ],
            'lines' => [[
                'designation' => trim((string) ($correspondence->subject ?: 'Courrier')),
                'quantity' => max(1, (int) ($correspondence->piece_count ?? 1)),
                'observation' => '',
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{presentation: array<string, string>, lines: list<array{designation: string, quantity: int, observation: string}>}
     */
    public function resolveSlipContent(Correspondence $correspondence, array $data): array
    {
        $suggested = $this->suggestSlip($correspondence);
        $presentation = $suggested['presentation'];
        $given = is_array($data['presentation'] ?? null) ? $data['presentation'] : [];
        foreach ($presentation as $key => $default) {
            if (array_key_exists($key, $given)) {
                $presentation[$key] = trim((string) $given[$key]);
            }
        }

        $lines = $this->normalizeLines($data['lines'] ?? null);

        return [
            'presentation' => $presentation,
            'lines' => $lines !== [] ? $lines : $suggested['lines'],
        ];
    }

    public function ensureSlipContent(CorrespondenceDispatch $dispatch, Correspondence $correspondence): CorrespondenceDispatch
    {
        $suggested = $this->suggestSlip($correspondence);
        if (! is_array($dispatch->lines) || $dispatch->lines === []) {
            $dispatch->lines = $suggested['lines'];
        } else {
            $dispatch->lines = $this->normalizeLines($dispatch->lines) ?: $suggested['lines'];
        }

        $presentation = $suggested['presentation'];
        if (is_array($dispatch->presentation)) {
            foreach ($presentation as $key => $default) {
                if (array_key_exists($key, $dispatch->presentation)) {
                    $presentation[$key] = trim((string) $dispatch->presentation[$key]);
                }
            }
        }
        $dispatch->presentation = $presentation;
        $dispatch->save();

        return $dispatch;
    }

    public function renderDispatchPdf(CorrespondenceDispatch $dispatch): string
    {
        $correspondence = $dispatch->correspondence()->with([
            'parties.correspondent',
            'registeredBy',
            'structure',
        ])->firstOrFail();
        $dispatch->loadMissing('dispatchedBy');
        if (! $dispatch->number) {
            $dispatch->number = $this->numberingService->generateDispatchSlipNumber($correspondence->structure_id);
            $dispatch->save();
        }
        $dispatch = $this->ensureSlipContent($dispatch, $correspondence);

        return $this->sheets->writeDispatchSlip($this->pdfPayload($dispatch, $correspondence));
    }

    /**
     * @param  mixed  $lines
     * @return list<array{designation: string, quantity: int, observation: string}>
     */
    private function normalizeLines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        $normalized = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $designation = trim((string) ($line['designation'] ?? ''));
            if ($designation === '') {
                continue;
            }
            $normalized[] = [
                'designation' => $designation,
                'quantity' => max(0, (int) ($line['quantity'] ?? 0)),
                'observation' => trim((string) ($line['observation'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function pdfPayload(CorrespondenceDispatch $dispatch, Correspondence $correspondence): array
    {
        $identity = $this->identity->forCorrespondence($correspondence);
        $presentation = is_array($dispatch->presentation) ? $dispatch->presentation : [];
        $lines = is_array($dispatch->lines) ? $dispatch->lines : [];
        $total = 0;
        foreach ($lines as $line) {
            $total += (int) ($line['quantity'] ?? 0);
        }

        $date = $dispatch->dispatched_at ?? now();

        return [
            'saisiPar' => $dispatch->dispatchedBy?->name ?? $correspondence->registeredBy?->name ?? '',
            'structureEntete' => $identity['header'] ?? '',
            'structureLine' => $identity['structure_line'] ?? null,
            'ministry' => $identity['ministry'] ?? null,
            'headerExtra' => $identity['extra'] ?? null,
            'footer' => $identity['footer'] ?? null,
            'republic' => $identity['republic'] ?? null,
            'motto' => $identity['motto'] ?? null,
            'emblem' => $this->dispatchLogoPath(),
            'place' => $presentation['place'] ?? '',
            'dateLong' => $this->frenchLongDate($date),
            'senderTitle' => $this->senderFormula($presentation, $identity),
            'recipientTitle' => $presentation['recipient_title'] ?? '',
            'recipientCity' => $presentation['recipient_city'] ?? '',
            'number' => $dispatch->number ?? '',
            'structureCode' => $presentation['structure_code'] ?? '',
            'lines' => $lines,
            'total' => $total,
            'signatoryRole' => $this->signatoryRole($identity),
            'signatoryName' => $this->signatoryName($identity),
            'signatoryGrade' => $this->signatoryGrade($identity),
        ];
    }

    private function frenchLongDate(\Carbon\CarbonInterface $date): string
    {
        $months = [1 => 'JAN', 2 => 'FEV', 3 => 'MARS', 4 => 'AVR', 5 => 'MAI', 6 => 'JUIN', 7 => 'JUIL', 8 => 'AOUT', 9 => 'SEPT', 10 => 'OCT', 11 => 'NOV', 12 => 'DEC'];

        return $date->format('d').' '.($months[(int) $date->format('n')] ?? $date->format('M')).' '.$date->format('Y');
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function signatoryRole(array $identity): string
    {
        $label = trim((string) ($identity['signatory_left'] ?? ''));
        if ($label === '' || $label === '—') {
            return 'LE DIRECTEUR GENERAL';
        }

        $upper = mb_strtoupper($label, 'UTF-8');
        if (! str_starts_with($upper, 'LE ') && ! str_starts_with($upper, 'LA ')) {
            $upper = 'LE '.$upper;
        }

        return $upper;
    }

    /**
     * @param  array<string, mixed>  $presentation
     * @param  array<string, mixed>  $identity
     */
    private function senderFormula(array $presentation, array $identity): string
    {
        $annex = 'Le Directeur Général du Trésor et de la Comptabilité Publique';
        $title = trim((string) ($presentation['sender_title'] ?? ''));
        $short = trim((string) ($identity['signatory_left'] ?? ''));
        if ($title === '' || $title === '—' || $this->sameLabel($title, $short) || $this->sameLabel($title, 'Le '.$short)) {
            return $annex;
        }

        return $title;
    }

    private function dispatchLogoPath(): ?string
    {
        $logo = public_path('logo.png');
        if (is_file($logo)) {
            return str_replace('\\', '/', $logo);
        }

        return MailSheetPdfService::emblemPath();
    }

    private function sameLabel(string $left, string $right): bool
    {
        $fold = static function (string $value): string {
            $value = mb_strtolower(trim($value), 'UTF-8');
            $value = strtr($value, [
                'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
                'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                'í' => 'i', 'î' => 'i', 'ï' => 'i',
                'ô' => 'o', 'ö' => 'o',
                'ù' => 'u', 'û' => 'u', 'ü' => 'u',
                'ç' => 'c',
            ]);

            return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
        };

        $a = $fold($left);
        $b = $fold($right);

        return $a !== '' && $a === $b;
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function signatoryName(array $identity): string
    {
        $userId = $identity['signatory_user_id'] ?? null;
        if (! $userId) {
            return '';
        }

        return trim((string) User::query()->whereKey($userId)->value('name'));
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function signatoryGrade(array $identity): string
    {
        $grade = trim((string) ($identity['signatory_function'] ?? ''));
        $left = trim((string) ($identity['signatory_left'] ?? ''));
        $right = trim((string) ($identity['signatory_right'] ?? ''));
        $sameAsRole = $grade === ''
            || $grade === '—'
            || mb_strtolower($grade) === mb_strtolower($left)
            || mb_strtolower($grade) === mb_strtolower($right);
        if ($sameAsRole) {
            $userId = $identity['signatory_user_id'] ?? null;
            $grade = $userId
                ? trim((string) User::query()->whereKey($userId)->value('position_title'))
                : '';
        }

        return $grade === '—' ? '' : $grade;
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

            $correspondence->loadMissing('structure');
            $boundaryId = $correspondence->structure?->boundary_structure_id ?: $correspondence->structure_id;

            $template = DocumentTemplate::query()
                ->where('kind', $kind->value)
                ->where('is_active', true)
                ->whereNotNull('current_published_version_id')
                ->where(function ($query) use ($boundaryId) {
                    $query->where('structure_id', $boundaryId)
                        ->orWhereNull('structure_id');
                })
                ->orderByRaw('case when structure_id is null then 1 else 0 end')
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
