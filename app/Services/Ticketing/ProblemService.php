<?php

namespace App\Services\Ticketing;

use App\Models\Problem;
use App\Models\Ticket;
use App\Models\User;
use App\Services\NumberingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProblemService
{
    public function __construct(
        private readonly NumberingService $numbering,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Problem::query()
            ->with(['owner:id,name', 'supportTeam:id,code,name'])
            ->orderByDesc('created_at');

        if (! empty($filters['q'])) {
            $q = '%'.addcslashes((string) $filters['q'], '%_\\').'%';
            $query->where(function ($qq) use ($q) {
                $qq->where('number', 'like', $q)
                    ->orWhere('title', 'like', $q);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['support_team_id'])) {
            $query->where('support_team_id', (int) $filters['support_team_id']);
        }

        return $query->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Problem
    {
        return DB::transaction(function () use ($actor, $data) {
            return Problem::query()->create([
                'number' => $this->numbering->generateProblemNumber($actor->structure_id),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'open',
                'root_cause' => $data['root_cause'] ?? null,
                'workaround' => $data['workaround'] ?? null,
                'owner_id' => $data['owner_id'] ?? $actor->id,
                'support_team_id' => $data['support_team_id'] ?? null,
            ])->fresh(['owner', 'supportTeam']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Problem $problem, array $data): Problem
    {
        $allowed = ['title', 'description', 'status', 'root_cause', 'workaround', 'owner_id', 'support_team_id'];
        $problem->update(array_intersect_key($data, array_flip($allowed)));

        return $problem->fresh(['owner', 'supportTeam', 'tickets']);
    }

    public function linkTicket(Problem $problem, Ticket $ticket): Problem
    {
        $problem->tickets()->syncWithoutDetaching([$ticket->id]);

        return $problem->fresh(['tickets']);
    }

    public function unlinkTicket(Problem $problem, Ticket $ticket): Problem
    {
        $problem->tickets()->detach($ticket->id);

        return $problem->fresh(['tickets']);
    }
}
