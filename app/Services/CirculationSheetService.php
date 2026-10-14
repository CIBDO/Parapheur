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
        private readonly MailSheetPdfService $pdf,
        private readonly MailIdentityService $identity,
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

    public function list(\App\Models\User $user, array $filters = [])
    {
        $query = CirculationSheet::query()->with(['correspondence', 'creator', 'document']);

        $query->whereHas('correspondence', function ($correspondence) use ($user) {
            app(MailVisibilityScope::class)->apply($correspondence, $user);
        });

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
            'structure.type',
            'assignments.toUser',
            'assignments.toStructure',
            'assignments.action',
        ])->firstOrFail();

        $this->identity->freeze($correspondence, force: ! $correspondence->dispatches()->exists());

        $variables = $this->buildVariables($sheet, $correspondence);

        try {
            $path = $this->pdf->writeCirculationSheet($variables);

            $uploaded = new \Illuminate\Http\UploadedFile(
                $path,
                MailSheetPdfService::safeFilename('fiche-circulation-'.$sheet->number.'.pdf'),
                'application/pdf',
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

        $priority = $correspondence->priorityCode();
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

        $priorityCode = $correspondence->priorityCode();
        $priorityLabel = \App\Models\CorrespondencePriority::query()->where('code', $priorityCode)->value('name')
            ?? match ($priorityCode) {
                'importante' => 'Importante',
                'urgente' => 'Urgente',
                'tres_urgente' => 'Très urgente',
                default => 'Normale',
            };

        $identity = $this->identity->forCorrespondence($correspondence);

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
                ?? ($correspondence->medium?->value === 'electronique' ? 'Email' : 'Lettre'),
            'qualification' => $correspondence->qualification?->name ?? '',
            'structure' => $correspondence->structure?->name ?? '',
            'enregistre_par' => $correspondence->registeredBy?->name ?? '',
            'saisi_par' => $correspondence->registeredBy?->name ?? '',
            'expediteur' => $fromParty?->name ?? $fromParty?->correspondent?->name ?? '',
            'expediteur_organisation' => $fromParty?->organization ?? $fromParty?->correspondent?->organization ?? '',
            'expediteur_fonction' => $fromParty?->function ?? '',
            'origine' => $fromParty?->correspondent?->sigle
                ?: $fromParty?->organization
                ?: $fromParty?->correspondent?->organization
                ?: $fromParty?->name
                ?: $fromParty?->correspondent?->name
                ?: '',
            'sens' => match ($correspondence->direction?->value) {
                'sortant' => 'DEPART',
                'interne' => 'INTERNE',
                default => 'ARRIVEE',
            },
            'numero_ministere' => $correspondence->ministry_number ?? '',
            'structure_entete' => $identity['header'],
            'structure_line' => $identity['structure_line'],
            'ministry' => $identity['ministry'],
            'header_extra' => $identity['extra'],
            'footer' => $identity['footer'],
            'republic' => $identity['republic'],
            'motto' => $identity['motto'],
            'signataire_gauche' => $identity['signatory_left'],
            'signataire_droite' => $identity['signatory_right'],
            'services_imputation' => $this->imputationColumns($correspondence),
            'annotations_grille' => $this->annotationGrid($correspondence, $selectedActions),
            'emblem' => MailSheetPdfService::emblemPath(),
            'destinataires' => $toParties->map(fn ($party) => $party->name ?? $party->correspondent?->name ?? '')
                ->filter()
                ->implode(', '),
            'imputations' => $imputations,
            'annotations' => $annotations,
            'affectations' => $correspondence->assignments->map(fn ($a) => [
                'service' => $a->toStructure?->name ?? '',
                'destinataire' => $a->toUser?->name ?? $a->toStructure?->name ?? '',
                'instruction' => $a->instruction_text ?? '',
                'action' => $a->action?->name ?? '',
            ])->values()->all(),
            'genere_le' => now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * Services à cocher à la main. L'imputation déjà faite n'est pas reprise ici.
     *
     * @return array<int, array{label: string}>
     */
    private function imputationColumns(Correspondence $correspondence): array
    {
        $parentId = $correspondence->structure_id;
        $structures = $parentId
            ? Structure::query()
                ->where('parent_id', $parentId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        if ($structures->isEmpty() && $correspondence->structure?->parent_id) {
            $structures = Structure::query()
                ->where('parent_id', $correspondence->structure->parent_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return $structures
            ->map(fn (Structure $structure) => ['label' => $structure->name])
            ->values()
            ->all();
    }

    /**
     * Grille d'annotations du modèle papier.
     *
     * @param  array<int, string>  $selectedActions
     * @return array<int, array<int, array{label: string, checked: bool}>>
     */
    private function annotationGrid(Correspondence $correspondence, array $selectedActions): array
    {
        $rows = [
            ['Pour attribution', 'Pour analyse', 'Pour information', 'Pour lecture'],
            ['Pour suivi', 'Pour étude et avis', 'Pour représentation', 'Pour supervision'],
            ['A classer', 'Pour éléments de rép.', 'Pour dispo. à prendre', 'M\'en parler'],
            ['En instance', 'Pour suite à donner', 'A photocopier', 'Pour Observation'],
            ['Pour exploitation', 'Synthèse', 'Pour diffusion', 'En parler au ...'],
            ['En rapport avec...', 'Urgent', 'Très urgent', 'Pour transmission à...'],
            ['Remis à l\'intéressé', 'A Saisir', 'M\'accompagner', 'Pour publication'],
        ];

        $byCode = [
            'POUR_ATTRIBUTION' => 'Pour attribution',
            'POUR_ANALYSE' => 'Pour analyse',
            'POUR_INFO' => 'Pour information',
            'POUR_LECTURE' => 'Pour lecture',
            'POUR_SUIVI' => 'Pour suivi',
            'POUR_AVIS' => 'Pour étude et avis',
            'SUITE_A_DONNER' => 'Pour suite à donner',
            'PREPARER_REPONSE' => 'Pour éléments de rép.',
            'A_CLASSER' => 'A classer',
            'A_PHOTOCOPIER' => 'A photocopier',
            'POUR_DIFFUSION' => 'Pour diffusion',
            'URGENT' => 'Urgent',
            'TRES_URGENT' => 'Très urgent',
        ];

        $checked = [];
        foreach ($selectedActions as $code) {
            if (isset($byCode[$code])) {
                $checked[$byCode[$code]] = true;
            }
        }

        $priority = $correspondence->priorityCode();
        if (in_array($priority, ['urgente', 'importante'], true)) {
            $checked['Urgent'] = true;
        }
        if ($priority === 'tres_urgente') {
            $checked['Très urgent'] = true;
        }

        $hints = $correspondence->assignments
            ->flatMap(fn ($assignment) => [$assignment->action?->name, $assignment->instruction_text])
            ->filter()
            ->map(fn ($text) => $this->fold((string) $text))
            ->all();

        $labels = array_merge(...$rows);
        usort($labels, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($hints as $hint) {
            foreach ($labels as $label) {
                $folded = $this->fold($label);
                if (mb_strlen($folded) >= 4 && str_contains($hint, $folded)) {
                    $checked[$label] = true;
                    break;
                }
            }
        }

        return array_map(function (array $row) use ($checked) {
            return array_map(fn (string $label) => [
                'label' => $label,
                'checked' => isset($checked[$label]),
            ], $row);
        }, $rows);
    }

    private function fold(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        return preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    }
}
