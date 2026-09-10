<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ParapheurFolder;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ParapheurService
{
    /**
     * Dossiers que l'utilisateur a transmis (suivi initiateur), y compris retournés / traités.
     */
    private function sentDocumentsQuery(User $user): Builder
    {
        return Document::query()
            ->where(function ($q) use ($user) {
                $q->whereHas('transmissions', fn ($t) => $t->where('from_user_id', $user->id))
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('author_id', $user->id)
                            ->whereNotNull('submitted_at');
                    });
            })
            ->where('status', '!=', DocumentStatus::Brouillon->value);
    }

    private function applyMetadataFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        if (! empty($filters['q'])) {
            $needle = trim((string) $filters['q']);
            $query->where(function ($q) use ($needle) {
                $q->where('reference', 'like', "%{$needle}%")
                    ->orWhere('object', 'like', "%{$needle}%");
            });
        }

        if (! empty($filters['reference'])) {
            $query->where('reference', 'like', '%'.trim((string) $filters['reference']).'%');
        }

        if (! empty($filters['object'])) {
            $query->where('object', 'like', '%'.trim((string) $filters['object']).'%');
        }

        foreach ([
            'document_type_id' => 'document_type_id',
            'structure_id' => 'structure_id',
            'author_id' => 'author_id',
            'status' => 'status',
            'priority' => 'priority',
            'confidentiality' => 'confidentiality',
        ] as $key => $column) {
            if (array_key_exists($key, $filters) && $filters[$key] !== null && $filters[$key] !== '') {
                $query->where($column, $filters[$key]);
            }
        }

        if (! empty($filters['document_date_from'])) {
            $query->whereDate('document_date', '>=', $filters['document_date_from']);
        }
        if (! empty($filters['document_date_to'])) {
            $query->whereDate('document_date', '<=', $filters['document_date_to']);
        }

        if (! empty($filters['due_date_from'])) {
            $query->whereDate('due_date', '>=', $filters['due_date_from']);
        }
        if (! empty($filters['due_date_to'])) {
            $query->whereDate('due_date', '<=', $filters['due_date_to']);
        }

        if (! empty($filters['keywords'])) {
            $raw = $filters['keywords'];
            $keywords = [];

            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $keywords = is_array($decoded)
                    ? $decoded
                    : array_values(array_filter(array_map('trim', explode(',', $raw))));
            }

            $keywords = array_values(array_filter(array_map('strval', $keywords)));

            if (! empty($keywords)) {
                $query->where(function ($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $q->orWhereJsonContains('keywords', $kw);
                    }
                });
            }
        }
    }

    public function countsFor(User $user): array
    {
        $base = \App\Models\DocumentTransmission::query()
            ->where('to_user_id', $user->id);

        $counts = [];
        foreach (ParapheurFolder::cases() as $folder) {
            if ($folder === ParapheurFolder::Envoyes) {
                $counts[$folder->value] = $this->sentDocumentsQuery($user)->count();

                continue;
            }

            $query = (clone $base)->where('folder', $folder->value);

            if (in_array($folder, [ParapheurFolder::Traites, ParapheurFolder::Archives], true)) {
                $counts[$folder->value] = $query->where('status', 'done')
                    ->distinct('document_id')
                    ->count('document_id');
            } elseif ($folder === ParapheurFolder::ATraiter) {
                $counts[$folder->value] = Document::query()
                    ->where(function ($q) use ($user) {
                        $q->where('current_assignee_id', $user->id)
                            ->orWhereHas('transmissions', function ($t) use ($user) {
                                $t->where('to_user_id', $user->id)
                                    ->whereIn('status', ['pending', 'seen'])
                                    ->whereIn('folder', [
                                        ParapheurFolder::ATraiter->value,
                                        ParapheurFolder::AConsulter->value,
                                        ParapheurFolder::AViser->value,
                                        ParapheurFolder::AValider->value,
                                    ]);
                            });
                    })
                    ->whereNotIn('status', [
                        DocumentStatus::Archive->value,
                        DocumentStatus::Annule->value,
                        DocumentStatus::Traite->value,
                        DocumentStatus::Classe->value,
                        DocumentStatus::Brouillon->value,
                    ])
                    ->count();
            } else {
                $counts[$folder->value] = $query->whereIn('status', ['pending', 'seen'])
                    ->distinct('document_id')
                    ->count('document_id');
            }
        }

        $counts['urgents'] = Document::query()
            ->where('current_assignee_id', $user->id)
            ->whereIn('priority', ['urgente', 'tres_urgente'])
            ->whereNotIn('status', ['archive', 'annule', 'valide', 'traite', 'classe'])
            ->count();

        return $counts;
    }

    public function listFolder(
        User $user,
        ?string $folder = null,
        array $filters = [],
        int $perPage = 15
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if ($user->can('admin.access') && $folder === null) {
            $query = Document::query()
                ->with(['type', 'structure', 'author', 'currentAssignee', 'latestVersion']);

            $this->applyMetadataFilters($query, $filters);

            return $query
                ->orderByRaw("CASE priority WHEN 'tres_urgente' THEN 1 WHEN 'urgente' THEN 2 WHEN 'importante' THEN 3 ELSE 4 END")
                ->orderByDesc('updated_at')
                ->paginate($perPage);
        }

        if ($folder === ParapheurFolder::Envoyes->value) {
            $query = $this->sentDocumentsQuery($user)
                ->with(['type', 'structure', 'author', 'currentAssignee', 'latestVersion']);

            $this->applyMetadataFilters($query, $filters);

            return $query
                ->orderByRaw("CASE priority WHEN 'tres_urgente' THEN 1 WHEN 'urgente' THEN 2 WHEN 'importante' THEN 3 ELSE 4 END")
                ->orderByDesc('updated_at')
                ->paginate($perPage);
        }

        $query = Document::query()
            ->with(['type', 'structure', 'author', 'currentAssignee', 'latestVersion']);

        if ($folder === ParapheurFolder::ATraiter->value) {
            $query->where(function ($q) use ($user) {
                $q->where('current_assignee_id', $user->id)
                    ->orWhereHas('transmissions', function ($t) use ($user) {
                        $t->where('to_user_id', $user->id)
                            ->whereIn('status', ['pending', 'seen'])
                            ->whereIn('folder', [
                                ParapheurFolder::ATraiter->value,
                                ParapheurFolder::AConsulter->value,
                                ParapheurFolder::AViser->value,
                                ParapheurFolder::AValider->value,
                            ]);
                    });
            })->whereNotIn('status', [
                DocumentStatus::Archive->value,
                DocumentStatus::Annule->value,
                DocumentStatus::Traite->value,
                DocumentStatus::Classe->value,
                DocumentStatus::Brouillon->value,
            ]);
        } elseif (in_array($folder, [ParapheurFolder::Traites->value, ParapheurFolder::Archives->value], true)) {
            $query->whereHas('transmissions', function ($t) use ($user, $folder) {
                $t->where('to_user_id', $user->id)
                    ->where('folder', $folder)
                    ->where('status', 'done');
            });
        } elseif ($folder) {
            $query->whereHas('transmissions', function ($t) use ($user, $folder) {
                $t->where('to_user_id', $user->id)
                    ->where('folder', $folder)
                    ->whereIn('status', ['pending', 'seen']);
            });
        } else {
            $query->where(function ($q) use ($user) {
                $q->where('current_assignee_id', $user->id)
                    ->orWhereHas('transmissions', fn ($t) => $t->where('to_user_id', $user->id));
            });
        }

        $this->applyMetadataFilters($query, $filters);

        return $query
            ->orderByRaw("CASE priority WHEN 'tres_urgente' THEN 1 WHEN 'urgente' THEN 2 WHEN 'importante' THEN 3 ELSE 4 END")
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function dashboardDg(): array
    {
        $byStatus = Document::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $byStructure = Document::query()
            ->join('structures', 'structures.id', '=', 'documents.structure_id')
            ->select('structures.code', DB::raw('count(*) as total'))
            ->groupBy('structures.code')
            ->pluck('total', 'code');

        $submitted = Document::query()->whereNotNull('submitted_at')->count();
        $returned = Document::query()->where('status', 'a_corriger')->count();
        $validatedOrTreated = Document::query()->whereIn('status', ['valide', 'traite', 'archive'])->count();
        $onTime = Document::query()
            ->whereIn('status', ['valide', 'traite', 'archive'])
            ->where(function ($q) {
                $q->whereNull('due_date')
                    ->orWhereColumn('archived_at', '<=', 'due_date')
                    ->orWhere(function ($q2) {
                        $q2->whereNull('archived_at')->whereRaw('DATE(updated_at) <= due_date');
                    });
            })
            ->count();

        $avgDaysExpression = DB::connection()->getDriverName() === 'sqlite'
            ? 'AVG(JULIANDAY(COALESCE(archived_at, updated_at)) - JULIANDAY(submitted_at)) as avg_days'
            : 'AVG(DATEDIFF(COALESCE(archived_at, updated_at), submitted_at)) as avg_days';

        $avgDays = Document::query()
            ->whereNotNull('submitted_at')
            ->whereIn('status', ['valide', 'traite', 'archive'])
            ->selectRaw($avgDaysExpression)
            ->value('avg_days');

        $avgByStructureExpression = DB::connection()->getDriverName() === 'sqlite'
            ? 'structures.code, AVG(JULIANDAY(COALESCE(documents.archived_at, documents.updated_at)) - JULIANDAY(documents.submitted_at)) as avg_days'
            : 'structures.code, AVG(DATEDIFF(COALESCE(documents.archived_at, documents.updated_at), documents.submitted_at)) as avg_days';

        $avgByStructure = Document::query()
            ->join('structures', 'structures.id', '=', 'documents.structure_id')
            ->whereNotNull('documents.submitted_at')
            ->whereIn('documents.status', ['valide', 'traite', 'archive'])
            ->selectRaw($avgByStructureExpression)
            ->groupBy('structures.code')
            ->pluck('avg_days', 'code')
            ->map(fn ($v) => round((float) $v, 1));

        $decisionsTotal = DB::table('meeting_decisions')->count();
        $decisionsDone = DB::table('instructions')
            ->whereNotNull('meeting_decision_id')
            ->whereIn('status', ['executee', 'cloturee'])
            ->count();

        return [
            'received' => $submitted,
            'to_process' => Document::query()->whereIn('status', ['transmis', 'en_circuit', 'a_consulter', 'a_viser', 'a_valider'])->count(),
            'urgent' => Document::query()->whereIn('priority', ['urgente', 'tres_urgente'])->whereNotIn('status', ['archive', 'valide', 'traite'])->count(),
            'overdue' => Document::query()->whereNotNull('due_date')->whereDate('due_date', '<', now())->whereNotIn('status', ['archive', 'valide', 'traite', 'classe'])->count(),
            'validated' => Document::query()->where('status', 'valide')->count(),
            'returned' => $returned,
            'by_status' => $byStatus->all(),
            'by_structure' => $byStructure->all(),
            'instructions_open' => DB::table('instructions')->whereIn('status', ['a_faire', 'en_cours'])->count(),
            'instructions_late' => DB::table('instructions')->whereIn('status', ['a_faire', 'en_cours'])->whereNotNull('due_date')->whereDate('due_date', '<', now())->count(),
            'avg_processing_days' => round((float) ($avgDays ?? 0), 1),
            'avg_processing_by_structure' => $avgByStructure->all(),
            'return_rate' => $submitted > 0 ? round(($returned / $submitted) * 100, 1) : 0,
            'electronic_treated' => $validatedOrTreated,
            'deadline_respect_rate' => $validatedOrTreated > 0 ? round(($onTime / $validatedOrTreated) * 100, 1) : 0,
            'decision_execution_rate' => $decisionsTotal > 0 ? round(($decisionsDone / $decisionsTotal) * 100, 1) : 0,
            'volume_by_structure' => $byStructure->all(),
            'meetings_today' => DB::table('meetings')->whereNull('deleted_at')->whereDate('meeting_date', now()->toDateString())->whereNotIn('status', ['annulee'])->count(),
            'meetings_this_week' => DB::table('meetings')->whereNull('deleted_at')->whereBetween('meeting_date', [now()->toDateString(), now()->endOfWeek()->toDateString()])->whereNotIn('status', ['annulee'])->count(),
            'meeting_decisions_open' => DB::table('meeting_decisions')->whereIn('status', ['a_faire', 'planifiee', 'en_cours', 'en_attente', 'bloquee', 'partiellement_executee', 'en_retard'])->count(),
            'meeting_decisions_late' => DB::table('meeting_decisions')->whereNotNull('due_date')->whereDate('due_date', '<', now())->whereNotIn('status', ['executee', 'cloturee', 'annulee'])->count(),
            'meeting_minutes_to_validate' => DB::table('meetings')->whereNull('deleted_at')->where('status', 'cr_en_validation')->count(),
        ];
    }
}
