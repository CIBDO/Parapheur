<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\ServiceCatalog;
use App\Models\ServiceItem;
use App\Models\ServiceItemField;
use App\Models\SlaCalendar;
use App\Models\SlaCalendarException;
use App\Models\SlaPolicy;
use App\Models\SupportTeam;
use App\Models\SupportTeamMember;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketChannel;
use App\Models\TicketPriorityMatrix;
use App\Models\TicketType;
use App\Models\TicketingSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingAdminController extends Controller
{
    private function guard(): void
    {
        $this->authorize('manageAdmin', Ticket::class);
    }

    // ========== Types ==========

    public function typesIndex(): JsonResponse
    {
        $this->guard();

        return response()->json(TicketType::query()->orderBy('sort_order')->get());
    }

    public function typesStore(Request $request): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:ticket_types,code',
            'name' => 'required|string|max:200',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $row = TicketType::query()->create($validated);

        return response()->json($row, 201);
    }

    public function typesUpdate(Request $request, TicketType $type): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:ticket_types,code,'.$type->id,
            'name' => 'sometimes|required|string|max:200',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $type->update($validated);

        return response()->json($type);
    }

    public function typesDestroy(TicketType $type): JsonResponse
    {
        $this->guard();

        $type->delete();

        return response()->json(['message' => 'Type supprimé']);
    }

    // ========== Catégories ==========

    public function categoriesIndex(): JsonResponse
    {
        $this->guard();

        return response()->json(
            TicketCategory::query()
                ->with('children')
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function categoriesStore(Request $request): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'parent_id' => 'nullable|exists:ticket_categories,id',
            'code' => 'required|string|max:50|unique:ticket_categories,code',
            'name' => 'required|string|max:200',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $row = TicketCategory::query()->create($validated);

        return response()->json($row, 201);
    }

    public function categoriesUpdate(Request $request, TicketCategory $category): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'parent_id' => 'nullable|exists:ticket_categories,id',
            'code' => 'sometimes|required|string|max:50|unique:ticket_categories,code,'.$category->id,
            'name' => 'sometimes|required|string|max:200',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function categoriesDestroy(TicketCategory $category): JsonResponse
    {
        $this->guard();

        $category->delete();

        return response()->json(['message' => 'Catégorie supprimée']);
    }

    // ========== Canaux ==========

    public function channelsIndex(): JsonResponse
    {
        $this->guard();

        return response()->json(TicketChannel::query()->orderBy('sort_order')->get());
    }

    public function channelsStore(Request $request): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:ticket_channels,code',
            'name' => 'required|string|max:200',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $row = TicketChannel::query()->create($validated);

        return response()->json($row, 201);
    }

    public function channelsUpdate(Request $request, TicketChannel $channel): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:ticket_channels,code,'.$channel->id,
            'name' => 'sometimes|required|string|max:200',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $channel->update($validated);

        return response()->json($channel);
    }

    public function channelsDestroy(TicketChannel $channel): JsonResponse
    {
        $this->guard();

        $channel->delete();

        return response()->json(['message' => 'Canal supprimé']);
    }

    // ========== Équipes ==========

    public function teamsIndex(): JsonResponse
    {
        $this->guard();

        return response()->json(
            SupportTeam::query()->with(['members.user:id,name,email'])->orderBy('name')->get()
        );
    }

    public function teamsStore(Request $request): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:support_teams,code',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $row = SupportTeam::query()->create($validated);

        return response()->json($row, 201);
    }

    public function teamsUpdate(Request $request, SupportTeam $team): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:support_teams,code,'.$team->id,
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $team->update($validated);

        return response()->json($team->fresh(['members.user:id,name,email']));
    }

    public function teamsDestroy(SupportTeam $team): JsonResponse
    {
        $this->guard();

        $team->delete();

        return response()->json(['message' => 'Équipe supprimée']);
    }

    public function teamMembersStore(Request $request, SupportTeam $team): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'level' => 'nullable|string|max:50',
            'is_lead' => 'nullable|boolean',
        ]);

        $member = SupportTeamMember::query()->updateOrCreate(
            ['support_team_id' => $team->id, 'user_id' => $validated['user_id']],
            [
                'level' => $validated['level'] ?? null,
                'is_lead' => (bool) ($validated['is_lead'] ?? false),
            ]
        );

        return response()->json($member->load('user:id,name,email'), 201);
    }

    public function teamMembersDestroy(SupportTeam $team, SupportTeamMember $member): JsonResponse
    {
        $this->guard();

        abort_unless((int) $member->support_team_id === (int) $team->id, 404);
        $member->delete();

        return response()->json(['message' => 'Membre retiré']);
    }

    // ========== Catalogue de services ==========

    public function catalogsIndex(): JsonResponse
    {
        $this->guard();

        return response()->json(
            ServiceCatalog::query()->with('items.fields')->orderBy('sort_order')->get()
        );
    }

    public function catalogsStore(Request $request): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:service_catalogs,code',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $row = ServiceCatalog::query()->create($validated);

        return response()->json($row, 201);
    }

    public function catalogsUpdate(Request $request, ServiceCatalog $catalog): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:service_catalogs,code,'.$catalog->id,
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $catalog->update($validated);

        return response()->json($catalog);
    }

    public function catalogsDestroy(ServiceCatalog $catalog): JsonResponse
    {
        $this->guard();

        $catalog->delete();

        return response()->json(['message' => 'Catalogue supprimé']);
    }

    public function itemsStore(Request $request, ServiceCatalog $catalog): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'ticket_type_id' => 'nullable|exists:ticket_types,id',
            'ticket_category_id' => 'nullable|exists:ticket_categories,id',
            'support_team_id' => 'nullable|exists:support_teams,id',
            'sla_policy_id' => 'nullable|exists:sla_policies,id',
            'default_priority_id' => 'nullable|exists:ticket_priorities,id',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['service_catalog_id'] = $catalog->id;
        $row = ServiceItem::query()->create($validated);

        return response()->json($row->load('fields'), 201);
    }

    public function itemsUpdate(Request $request, ServiceItem $item): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'ticket_type_id' => 'nullable|exists:ticket_types,id',
            'ticket_category_id' => 'nullable|exists:ticket_categories,id',
            'support_team_id' => 'nullable|exists:support_teams,id',
            'sla_policy_id' => 'nullable|exists:sla_policies,id',
            'default_priority_id' => 'nullable|exists:ticket_priorities,id',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $item->update($validated);

        return response()->json($item->fresh('fields'));
    }

    public function itemsDestroy(ServiceItem $item): JsonResponse
    {
        $this->guard();

        $item->delete();

        return response()->json(['message' => 'Item de service supprimé']);
    }

    public function fieldsStore(Request $request, ServiceItem $item): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'label' => 'required|string|max:200',
            'field_type' => 'required|string|max:50',
            'is_required' => 'nullable|boolean',
            'options' => 'nullable|array',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['service_item_id'] = $item->id;
        $row = ServiceItemField::query()->create($validated);

        return response()->json($row, 201);
    }

    public function fieldsUpdate(Request $request, ServiceItemField $field): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50',
            'label' => 'sometimes|required|string|max:200',
            'field_type' => 'sometimes|required|string|max:50',
            'is_required' => 'nullable|boolean',
            'options' => 'nullable|array',
            'sort_order' => 'nullable|integer',
        ]);

        $field->update($validated);

        return response()->json($field);
    }

    public function fieldsDestroy(ServiceItemField $field): JsonResponse
    {
        $this->guard();

        $field->delete();

        return response()->json(['message' => 'Champ supprimé']);
    }

    // ========== SLA ==========

    public function slaPoliciesIndex(): JsonResponse
    {
        $this->guard();

        return response()->json(
            SlaPolicy::query()->with(['priority', 'calendar'])->orderBy('name')->get()
        );
    }

    public function slaPoliciesStore(Request $request): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:sla_policies,code',
            'name' => 'required|string|max:200',
            'priority_id' => 'nullable|exists:ticket_priorities,id',
            'sla_calendar_id' => 'nullable|exists:sla_calendars,id',
            'response_minutes' => 'required|integer|min:1',
            'resolution_minutes' => 'required|integer|min:1',
            'warning_percent' => 'nullable|integer|min:1|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $row = SlaPolicy::query()->create($validated);

        return response()->json($row->load(['priority', 'calendar']), 201);
    }

    public function slaPoliciesUpdate(Request $request, SlaPolicy $policy): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:sla_policies,code,'.$policy->id,
            'name' => 'sometimes|required|string|max:200',
            'priority_id' => 'nullable|exists:ticket_priorities,id',
            'sla_calendar_id' => 'nullable|exists:sla_calendars,id',
            'response_minutes' => 'sometimes|integer|min:1',
            'resolution_minutes' => 'sometimes|integer|min:1',
            'warning_percent' => 'nullable|integer|min:1|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $policy->update($validated);

        return response()->json($policy->fresh(['priority', 'calendar']));
    }

    public function slaPoliciesDestroy(SlaPolicy $policy): JsonResponse
    {
        $this->guard();

        $policy->delete();

        return response()->json(['message' => 'Politique SLA supprimée']);
    }

    public function slaCalendarsIndex(): JsonResponse
    {
        $this->guard();

        return response()->json(
            SlaCalendar::query()->with('exceptions')->orderBy('name')->get()
        );
    }

    public function slaCalendarsStore(Request $request): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:sla_calendars,code',
            'name' => 'required|string|max:200',
            'weekdays' => 'nullable|array',
            'start_time' => 'nullable|string|max:8',
            'end_time' => 'nullable|string|max:8',
            'is_24_7' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $row = SlaCalendar::query()->create($validated);

        return response()->json($row, 201);
    }

    public function slaCalendarsUpdate(Request $request, SlaCalendar $calendar): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:sla_calendars,code,'.$calendar->id,
            'name' => 'sometimes|required|string|max:200',
            'weekdays' => 'nullable|array',
            'start_time' => 'nullable|string|max:8',
            'end_time' => 'nullable|string|max:8',
            'is_24_7' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $calendar->update($validated);

        return response()->json($calendar->fresh('exceptions'));
    }

    public function slaCalendarsDestroy(SlaCalendar $calendar): JsonResponse
    {
        $this->guard();

        $calendar->delete();

        return response()->json(['message' => 'Calendrier SLA supprimé']);
    }

    public function slaCalendarExceptionsStore(Request $request, SlaCalendar $calendar): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'date' => 'required|date',
            'is_closed' => 'nullable|boolean',
            'start_time' => 'nullable|string|max:8',
            'end_time' => 'nullable|string|max:8',
            'label' => 'nullable|string|max:200',
        ]);

        $validated['sla_calendar_id'] = $calendar->id;
        $row = SlaCalendarException::query()->create($validated);

        return response()->json($row, 201);
    }

    // ========== Matrice priorités ==========

    public function priorityMatrixIndex(): JsonResponse
    {
        $this->guard();

        return response()->json(
            TicketPriorityMatrix::query()->with(['impact', 'urgency', 'priority'])->get()
        );
    }

    public function priorityMatrixStore(Request $request): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'impact_id' => 'required|exists:ticket_impact_levels,id',
            'urgency_id' => 'required|exists:ticket_urgency_levels,id',
            'priority_id' => 'required|exists:ticket_priorities,id',
        ]);

        $row = TicketPriorityMatrix::query()->updateOrCreate(
            [
                'impact_id' => $validated['impact_id'],
                'urgency_id' => $validated['urgency_id'],
            ],
            ['priority_id' => $validated['priority_id']]
        );

        return response()->json($row->load(['impact', 'urgency', 'priority']), 201);
    }

    public function priorityMatrixDestroy(TicketPriorityMatrix $matrix): JsonResponse
    {
        $this->guard();

        $matrix->delete();

        return response()->json(['message' => 'Entrée matrice supprimée']);
    }

    // ========== Paramètres ==========

    public function settingsIndex(): JsonResponse
    {
        $this->guard();

        return response()->json(TicketingSetting::query()->orderBy('key')->get());
    }

    public function settingsUpdate(Request $request): JsonResponse
    {
        $this->guard();

        $validated = $request->validate([
            'auto_close_days' => 'nullable|integer|min:1|max:365',
            'settings' => 'nullable|array',
        ]);

        if (isset($validated['auto_close_days'])) {
            TicketingSetting::query()->updateOrCreate(
                ['key' => 'auto_close_days'],
                ['value' => ['days' => (int) $validated['auto_close_days']]]
            );
        }

        if (! empty($validated['settings']) && is_array($validated['settings'])) {
            foreach ($validated['settings'] as $key => $value) {
                TicketingSetting::query()->updateOrCreate(
                    ['key' => (string) $key],
                    ['value' => is_array($value) ? $value : ['value' => $value]]
                );
            }
        }

        return response()->json(TicketingSetting::query()->orderBy('key')->get());
    }
}
