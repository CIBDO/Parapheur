<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\ServiceItem;
use App\Models\Ticket;
use App\Services\Ticketing\ServiceCatalogService;
use Illuminate\Http\JsonResponse;

class ServiceCatalogController extends Controller
{
    public function __construct(
        private readonly ServiceCatalogService $catalog,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        return response()->json($this->catalog->listActive());
    }

    public function show(ServiceItem $serviceItem): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $serviceItem->load([
            'fields',
            'catalog:id,code,name',
            'ticketType:id,code,name',
            'ticketCategory:id,code,name',
            'supportTeam:id,code,name',
            'slaPolicy:id,code,name',
            'defaultPriority:id,code,name,level',
        ]);

        return response()->json($serviceItem);
    }

    public function form(ServiceItem $serviceItem): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $serviceItem->load([
            'fields',
            'catalog:id,code,name',
            'ticketType:id,code,name',
            'ticketCategory:id,code,name',
            'supportTeam:id,code,name',
            'slaPolicy:id,code,name',
            'defaultPriority:id,code,name,level',
        ]);

        return response()->json([
            'item' => $serviceItem,
            'fields' => $serviceItem->fields,
        ]);
    }
}
