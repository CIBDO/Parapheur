<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\DB;

class ParapheurService
{
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

    public function countsFor(\App\Models\User $user): array
    {
        $base = \App\Models\DocumentTransmission::query()
            ->where('to_user_id', $user->id);

        $counts = [];
        foreach (\App\Enums\ParapheurFolder::cases() as $folder) {
            $query = (clone $base)->where('folder', $folder->value);

            if (in_array($folder, [\App\Enums\ParapheurFolder::Traites, \App\Enums\ParapheurFolder::Archives], true)) {
                $counts[$folder->value] = $query->where('status', 'done')
                    ->distinct('document_id')
                    ->count('document_id');
            } elseif ($folder === \App\Enums\ParapheurFolder::ATraiter) {
                $counts[$folder->value] = Document::query()
                    ->where(function ($q) use ($user) {
                        $q->where('current_assignee_id', $user->id)
                            ->orWhereHas('transmissions', function ($t) use ($user) {
                                $t->where('to_user_id', $user->id)
                                    ->whereIn('status', ['pending', 'seen'])
                                    ->whereIn('folder', [
                                        \App\Enums\ParapheurFolder::ATraiter->value,
                                        \App\Enums\ParapheurFolder::AConsulter->value,
                                        \App\Enums\ParapheurFolder::AViser->value,
                                        \App\Enums\ParapheurFolder::AValider->value,
                                    ]);
                            });
                    })
                    ->whereNotIn('status', [
                        \App\Enums\DocumentStatus::Archive->value,
                        \App\Enums\DocumentStatus::Annule->value,
                        \App\Enums\DocumentStatus::Traite->value,
                        \App\Enums\DocumentStatus::Classe->value,
                        \App\Enums\DocumentStatus::Brouillon->value,
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
        \App\Models\User $user,
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

        $query = Document::query()
            ->with(['type', 'structure', 'author', 'currentAssignee', 'latestVersion']);

        if ($folder === \App\Enums\ParapheurFolder::ATraiter->value) {
            $query->where(function ($q) use ($user) {
                $q->where('current_assignee_id', $user->id)
                    ->orWhereHas('transmissions', function ($t) use ($user) {
                        $t->where('to_user_id', $user->id)
                            ->whereIn('status', ['pending', 'seen'])
                            ->whereIn('folder', [
                                \App\Enums\ParapheurFolder::ATraiter->value,
                                \App\Enums\ParapheurFolder::AConsulter->value,
                                \App\Enums\ParapheurFolder::AViser->value,
                                \App\Enums\ParapheurFolder::AValider->value,
                            ]);
                    });
            })->whereNotIn('status', [
                \App\Enums\DocumentStatus::Archive->value,
                \App\Enums\DocumentStatus::Annule->value,
                \App\Enums\DocumentStatus::Traite->value,
                \App\Enums\DocumentStatus::Classe->value,
                \App\Enums\DocumentStatus::Brouillon->value,
            ]);
        } elseif (in_array($folder, [\App\Enums\ParapheurFolder::Traites->value, \App\Enums\ParapheurFolder::Archives->value], true)) {
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

        $avgDays = Document::query()
            ->whereNotNull('submitted_at')
            ->whereIn('status', ['valide', 'traite', 'archive'])
            ->selectRaw('AVG(JULIANDAY(COALESCE(archived_at, updated_at)) - JULIANDAY(submitted_at)) as avg_days')
            ->value('avg_days');

        // MySQL/MariaDB compatible average
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $avgDays = Document::query()
                ->whereNotNull('submitted_at')
                ->whereIn('status', ['valide', 'traite', 'archive'])
                ->selectRaw('AVG(DATEDIFF(COALESCE(archived_at, updated_at), submitted_at)) as avg_days')
                ->value('avg_days');
        }

        $avgByStructure = Document::query()
            ->join('structures', 'structures.id', '=', 'documents.structure_id')
            ->whereNotNull('documents.submitted_at')
            ->whereIn('documents.status', ['valide', 'traite', 'archive'])
            ->when(
                DB::connection()->getDriverName() === 'sqlite',
                fn ($q) => $q->selectRaw('structures.code, AVG(JULIANDAY(COALESCE(documents.archived_at, documents.updated_at)) - JULIANDAY(documents.submitted_at)) as avg_days'),
                fn ($q) => $q->selectRaw('structures.code, AVG(DATEDIFF(COALESCE(documents.archived_at, documents.updated_at), documents.submitted_at)) as avg_days'),
            )
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
            'by_status' => $byStatus,
            'by_structure' => $byStructure,
            'instructions_open' => DB::table('instructions')->whereIn('status', ['a_faire', 'en_cours'])->count(),
            'instructions_late' => DB::table('instructions')->whereIn('status', ['a_faire', 'en_cours'])->whereNotNull('due_date')->whereDate('due_date', '<', now())->count(),
            'avg_processing_days' => round((float) ($avgDays ?? 0), 1),
            'avg_processing_by_structure' => $avgByStructure,
            'return_rate' => $submitted > 0 ? round(($returned / $submitted) * 100, 1) : 0,
            'electronic_treated' => $validatedOrTreated,
            'deadline_respect_rate' => $validatedOrTreated > 0 ? round(($onTime / $validatedOrTreated) * 100, 1) : 0,
            'decision_execution_rate' => $decisionsTotal > 0 ? round(($decisionsDone / $decisionsTotal) * 100, 1) : 0,
            'volume_by_structure' => $byStructure,
        ];
    }
}
