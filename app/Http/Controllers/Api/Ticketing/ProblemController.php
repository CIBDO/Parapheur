<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Problem;
use App\Models\Ticket;
use App\Services\Ticketing\ProblemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProblemController extends Controller
{
    public function __construct(
        private readonly ProblemService $problems,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('problem.view')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        return response()->json($this->problems->list($request->only([
            'q', 'status', 'support_team_id', 'per_page',
        ])));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('problem.create')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'root_cause' => 'nullable|string',
            'workaround' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
            'support_team_id' => 'nullable|exists:support_teams,id',
        ]);

        $problem = $this->problems->create($request->user(), $validated);

        return response()->json($problem, 201);
    }

    public function show(Request $request, Problem $problem): JsonResponse
    {
        abort_unless(
            $request->user()->can('problem.view')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        $problem->load(['owner:id,name', 'supportTeam:id,code,name', 'tickets:id,number,title,status', 'knownErrors']);

        return response()->json($problem);
    }

    public function update(Request $request, Problem $problem): JsonResponse
    {
        abort_unless(
            $request->user()->can('problem.update')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'root_cause' => 'nullable|string',
            'workaround' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
            'support_team_id' => 'nullable|exists:support_teams,id',
        ]);

        return response()->json($this->problems->update($problem, $validated));
    }

    public function linkTicket(Request $request, Problem $problem): JsonResponse
    {
        abort_unless(
            $request->user()->can('problem.update')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        $validated = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
        ]);

        $ticket = Ticket::query()->findOrFail($validated['ticket_id']);

        return response()->json($this->problems->linkTicket($problem, $ticket));
    }

    public function unlinkTicket(Request $request, Problem $problem, Ticket $ticket): JsonResponse
    {
        abort_unless(
            $request->user()->can('problem.update')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        return response()->json($this->problems->unlinkTicket($problem, $ticket));
    }
}
