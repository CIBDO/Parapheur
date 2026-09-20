<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\ServiceCatalog;
use App\Models\SupportTeam;
use App\Models\TicketCategory;
use App\Models\TicketChannel;
use App\Models\TicketImpactLevel;
use App\Models\TicketPriority;
use App\Models\TicketStatusRef;
use App\Models\TicketTag;
use App\Models\TicketType;
use App\Models\TicketUrgencyLevel;
use Illuminate\Http\JsonResponse;

class TicketingMetaController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Ticket::class);

        $categories = TicketCategory::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'types' => TicketType::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name', 'sort_order']),
            'categories' => $categories,
            'priorities' => TicketPriority::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name', 'level', 'sort_order']),
            'impacts' => TicketImpactLevel::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name', 'level', 'sort_order']),
            'urgencies' => TicketUrgencyLevel::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name', 'level', 'sort_order']),
            'channels' => TicketChannel::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name', 'sort_order']),
            'teams' => SupportTeam::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->with(['members.user:id,name'])
                ->get(['id', 'code', 'name']),
            'statuses' => TicketStatusRef::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name', 'pauses_sla', 'sort_order']),
            'tags' => TicketTag::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'color']),
            'catalog' => ServiceCatalog::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->with(['items' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->select(['id', 'service_catalog_id', 'code', 'name', 'sort_order'])])
                ->get(['id', 'code', 'name', 'description', 'sort_order']),
        ]);
    }
}
