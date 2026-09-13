<?php

namespace App\Services;

use App\Enums\NumberingSequenceCode;
use App\Models\CirculationSheet;
use App\Models\Correspondence;
use App\Models\CorrespondenceAssignmentAction;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class CirculationSheetService
{
    public function __construct(
        private readonly NumberingService $numberingService,
        private readonly DocumentTemplateService $templateService,
        private readonly DocumentTemplateRenderer $renderer,
        private readonly DocumentService $documentService,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Une seule fiche de circulation par courrier : réutilise l'existante si présente.
     *
     * @return array{sheet: CirculationSheet, created: bool}
     */
    public function findOrCreate(Correspondence $correspondence, User $user, array $data = []): array
    {
        return DB::transaction(function () use ($correspondence, $user, $data) {
            $existing = CirculationSheet::query()
                ->where('correspondence_id', $correspondence->id)
                ->orderByRaw('CASE WHEN document_id IS NULL THEN 1 ELSE 0 END')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [
                    'sheet' => $existing->fresh(['correspondence', 'document', 'creator']),
                    'created' => false,
                ];
            }

            $number = $this->numberingService->nextNumber(
                NumberingSequenceCode::CirculationSheet,
                null,
                $correspondence->structure_id
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

            return [
                'sheet' => $sheet->fresh(['correspondence', 'document', 'creator']),
                'created' => true,
            ];
        });
    }

    /**
     * @deprecated Préférer findOrCreate() — conserve une seule fiche par courrier.
     */
    public function create(Correspondence $correspondence, User $user, array $data = []): CirculationSheet
    {
        return $this->findOrCreate($correspondence, $user, $data)['sheet'];
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
     * Génère le document de la fiche de circulation (modèle DGTCP).
     */
    public function generateDocument(CirculationSheet $sheet, User $user): CirculationSheet
    {
        $correspondence = $sheet->correspondence()->with([
            'parties.correspondent',
            'channel',
            'category',
            'qualification',
            'registeredBy',
            'structure',
            'assignments.toUser',
            'assignments.toStructure',
            'assignments.action',
        ])->firstOrFail();

        $variables = $this->buildVariables($sheet, $correspondence);

        try {
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
                $path = $this->renderer->createCirculationSheetDocx($variables);

                $uploaded = new \Illuminate\Http\UploadedFile(
                    $path,
                    'fiche-circulation-'.$sheet->number.'.docx',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    null,
                    true
                );

                if ($sheet->document_id && ($existing = \App\Models\Document::query()->find($sheet->document_id))) {
                    $this->documentService->addVersion($existing, $user, $uploaded, 'Régénération fiche de circulation');
                    $existing->title = 'Fiche de circulation — '.$sheet->number;
                    $existing->object = 'Fiche circulation '.$sheet->number;
                    $existing->save();
                    $document = $existing->fresh();
                } elseif ($sheet->number && ($existing = \App\Models\Document::query()->where('reference', $sheet->number)->first())) {
                    $this->documentService->addVersion($existing, $user, $uploaded, 'Régénération fiche de circulation');
                    $document = $existing->fresh();
                } else {
                    $document = $this->documentService->create($user, [
                        'origin' => 'courrier',
                        'object' => 'Fiche circulation '.$sheet->number,
                        'title' => 'Fiche de circulation — '.$sheet->number,
                        'reference' => $sheet->number,
                        'document_type_id' => \App\Models\DocumentType::query()->value('id'),
                        'structure_id' => $correspondence->structure_id,
                        'confidentiality' => $correspondence->confidentiality?->value,
                    ], $uploaded);
                }

                @unlink($path);
            }
        } catch (Throwable $e) {
            throw new \InvalidArgumentException(
                'Impossible de générer le document de fiche : '.$e->getMessage(),
                previous: $e
            );
        }

        $sheet->document_id = $document->id;
        $sheet->save();

        $this->audit->log('circulation_sheet.document_generated', $sheet, [
            'actor_id' => $user->id,
            'document_id' => $document->id,
        ]);

        return $sheet->fresh(['document', 'correspondence', 'creator']);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildVariables(CirculationSheet $sheet, Correspondence $correspondence): array
    {
        $roleValue = static fn ($party): string => $party->role instanceof \BackedEnum
            ? $party->role->value
            : (string) ($party->role ?? '');

        $fromParty = $correspondence->parties->first(fn ($party) => $roleValue($party) === 'from');
        $toParties = $correspondence->parties->filter(fn ($party) => $roleValue($party) === 'to');

        $assignedStructureIds = $correspondence->assignments
            ->pluck('to_structure_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $assignedUserStructures = $correspondence->assignments
            ->map(fn ($a) => $a->toUser?->structure_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $imputedIds = array_values(array_unique(array_merge(
            $assignedStructureIds,
            $assignedUserStructures,
            $correspondence->structure_id ? [(int) $correspondence->structure_id] : []
        )));

        $selectedActions = $correspondence->assignments
            ->map(fn ($a) => $a->action?->code)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($correspondence->qualification?->code) {
            $selectedActions[] = $correspondence->qualification->code;
        }

        $dbAnnotations = CorrespondenceAssignmentAction::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name'])
            ->keyBy('code');

        // Grille d'annotations type fiche papier DGTCP + référentiel métier
        $classicAnnotations = [
            ['code' => 'POUR_ATTRIBUTION', 'label' => 'Pour attribution'],
            ['code' => 'POUR_ANALYSE', 'label' => 'Pour analyse'],
            ['code' => 'POUR_LECTURE', 'label' => 'Pour lecture'],
            ['code' => 'POUR_SUIVI', 'label' => 'Pour suivi'],
            ['code' => 'POUR_AVIS', 'label' => 'Pour étude et avis'],
            ['code' => 'POUR_INFO', 'label' => 'Pour information'],
            ['code' => 'SUITE_A_DONNER', 'label' => 'Pour suite à donner'],
            ['code' => 'PREPARER_REPONSE', 'label' => 'Pour éléments de réponse'],
            ['code' => 'TRAITER', 'label' => 'Pour traitement'],
            ['code' => 'DONNER_AVIS', 'label' => 'Donner un avis'],
            ['code' => 'A_CLASSER', 'label' => 'À classer'],
            ['code' => 'A_PHOTOCOPIER', 'label' => 'À photocopier'],
            ['code' => 'POUR_DIFFUSION', 'label' => 'Pour diffusion'],
            ['code' => 'URGENT', 'label' => 'Urgent'],
            ['code' => 'TRES_URGENT', 'label' => 'Très urgent'],
            ['code' => 'ARCHIVER', 'label' => 'Archiver'],
        ];

        foreach ($dbAnnotations as $code => $action) {
            if (! collect($classicAnnotations)->contains(fn ($item) => $item['code'] === $code)) {
                $classicAnnotations[] = ['code' => $code, 'label' => $action->name];
            }
        }

        $priority = $correspondence->priority?->value;
        $annotations = array_map(function (array $item) use ($priority, $selectedActions, $dbAnnotations) {
            $label = $dbAnnotations[$item['code']]->name ?? $item['label'];
            $checked = in_array($item['code'], $selectedActions, true)
                || ($item['code'] === 'URGENT' && in_array($priority, ['urgente', 'importante'], true))
                || ($item['code'] === 'TRES_URGENT' && $priority === 'tres_urgente');

            return [
                'code' => $item['code'],
                'label' => $label,
                'checked' => $checked,
            ];
        }, $classicAnnotations);

        // Imputation : structures racines puis directions (éviter trop de lignes)
        $structures = Structure::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'parent_id']);

        $rootIds = $structures->whereNull('parent_id')->pluck('id')->all();
        $imputations = $structures
            ->filter(fn (Structure $s) => $s->parent_id === null || in_array((int) $s->parent_id, $rootIds, true))
            ->take(24)
            ->map(fn (Structure $structure) => [
                'code' => $structure->code,
                'label' => $structure->name,
                'checked' => in_array((int) $structure->id, $imputedIds, true),
            ])
            ->values()
            ->all();

        $confidentiality = $correspondence->confidentiality?->value ?? 'normal';
        $confidentialLabel = match ($confidentiality) {
            'confidentiel', 'tres_confidentiel' => 'CONFIDENTIEL',
            'restreint' => 'RESTREINT',
            default => 'ORDINAIRE',
        };

        $priorityLabel = match ($correspondence->priority?->value) {
            'importante' => 'Importante',
            'urgente' => 'Urgente',
            'tres_urgente' => 'Très urgente',
            default => 'Normale',
        };

        return [
            'numero_fiche' => $sheet->number,
            'numero_arrivee' => $correspondence->arrival_number,
            'numero_depart' => $correspondence->departure_number,
            'numero_enregistrement' => $correspondence->arrival_number
                ?: $correspondence->departure_number
                ?: $sheet->number,
            'date_enregistrement' => $correspondence->registered_at?->format('d/m/Y') ?? '',
            'objet' => $correspondence->subject,
            'resume' => $correspondence->summary ?? '',
            'observations' => $correspondence->observations ?? '',
            'reference_externe' => $correspondence->external_reference ?? '',
            'date_correspondance' => $correspondence->correspondence_date?->format('d/m/Y') ?? '',
            'date_reception' => $correspondence->received_at?->format('d/m/Y')
                ?? $correspondence->registered_at?->format('d/m/Y')
                ?? '',
            'date_echeance' => $correspondence->due_date?->format('d/m/Y') ?? '',
            'priorite' => $priorityLabel,
            'confidentialite' => $confidentiality,
            'confidentialite_label' => $confidentialLabel,
            'nombre_pieces' => $correspondence->piece_count ?? 0,
            'canal' => $correspondence->channel?->name ?? '',
            'categorie' => $correspondence->category?->name ?? '',
            'type_courrier' => $correspondence->category?->name
                ?? $correspondence->channel?->name
                ?? ($correspondence->medium?->value === 'electronique' ? 'Email' : 'Lettre'),
            'qualification' => $correspondence->qualification?->name ?? '',
            'structure' => $correspondence->structure?->name ?? '',
            'enregistre_par' => $correspondence->registeredBy?->name ?? '',
            'saisi_par' => $correspondence->registeredBy?->name ?? '',
            'expediteur' => $fromParty?->name ?? $fromParty?->correspondent?->name ?? '',
            'expediteur_organisation' => $fromParty?->organization ?? $fromParty?->correspondent?->organization ?? '',
            'expediteur_fonction' => $fromParty?->function ?? '',
            'origine' => $fromParty?->organization
                ?? $fromParty?->correspondent?->organization
                ?? $fromParty?->name
                ?? $fromParty?->correspondent?->name
                ?? '',
            'destinataires' => $toParties->map(fn ($party) => $party->name ?? $party->correspondent?->name ?? '')
                ->filter()
                ->implode(', '),
            'imputations' => $imputations,
            'annotations' => $annotations,
            'affectations' => $correspondence->assignments->map(fn ($a) => [
                'destinataire' => $a->toUser?->name ?? $a->toStructure?->name ?? '',
                'instruction' => $a->instruction_text ?? '',
                'action' => $a->action?->name ?? '',
            ])->values()->all(),
            'genere_le' => now()->format('d/m/Y H:i'),
        ];
    }
}
