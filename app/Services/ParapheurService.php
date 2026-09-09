<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ParapheurFolder;
use App\Models\Document;
use App\Models\DocumentTransmission;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ParapheurService
{
    public function countsFor(User $user): array
    {
        $base = DocumentTransmission::query()
            ->where('to_user_id', $user->id);

        $counts = [];
        foreach (ParapheurFolder::cases() as $folder) {
            $query = (clone $base)->where('folder', $folder->value);

            if (in_array($folder, [ParapheurFolder::Traites, ParapheurFolder::Archives], true)) {
                $counts[$folder->value] = $query->where('status', 'done')
                    ->distinct('document_id')
                    ->count('document_id');
            } elseif ($folder === ParapheurFolder::ATraiter) {
                // Corbeille transverse : dossiers assignés ou transmis en attente d'action
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

    public function listFolder(User $user, ?string $folder = null, int $perPage = 15): LengthAwarePaginator
    {
        if ($user->can('admin.access') && $folder === null) {
            return Document::query()
                ->with(['type', 'structure', 'author', 'currentAssignee', 'latestVersion'])
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

        return [
            'received' => Document::query()->whereNotNull('submitted_at')->count(),
            'to_process' => Document::query()->whereIn('status', ['transmis', 'en_circuit', 'a_consulter', 'a_viser', 'a_valider'])->count(),
            'urgent' => Document::query()->whereIn('priority', ['urgente', 'tres_urgente'])->whereNotIn('status', ['archive', 'valide', 'traite'])->count(),
            'overdue' => Document::query()->whereNotNull('due_date')->whereDate('due_date', '<', now())->whereNotIn('status', ['archive', 'valide', 'traite', 'classe'])->count(),
            'validated' => Document::query()->where('status', 'valide')->count(),
            'returned' => Document::query()->where('status', 'a_corriger')->count(),
            'by_status' => $byStatus,
            'by_structure' => $byStructure,
            'instructions_open' => DB::table('instructions')->whereIn('status', ['a_faire', 'en_cours'])->count(),
            'instructions_late' => DB::table('instructions')->whereIn('status', ['a_faire', 'en_cours'])->whereDate('due_date', '<', now())->count(),
        ];
    }
}
