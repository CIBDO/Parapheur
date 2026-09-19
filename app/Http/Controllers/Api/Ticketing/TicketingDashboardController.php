<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Ticketing\TicketReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingDashboardController extends Controller
{
    public function __construct(
        private readonly TicketReportingService $reporting,
    ) {}

    public function requester(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        return response()->json($this->reporting->dashboardRequester($request->user()));
    }

    public function agent(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        return response()->json($this->reporting->dashboardAgent($request->user()));
    }

    public function team(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $validated = $request->validate([
            'team_id' => 'nullable|exists:support_teams,id',
        ]);

        return response()->json($this->reporting->dashboardTeam(
            $request->user(),
            isset($validated['team_id']) ? (int) $validated['team_id'] : null,
        ));
    }

    public function management(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('ticket.view_reports')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        return response()->json($this->reporting->dashboardManagement());
    }
}
