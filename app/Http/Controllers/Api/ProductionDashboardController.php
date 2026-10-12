<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Productions\ProductionDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionDashboardController extends Controller
{
    public function __construct(
        private readonly ProductionDashboardService $dashboard,
    ) {}

    public function myWork(Request $request): JsonResponse
    {
        return response()->json($this->dashboard->myWork($request->user()));
    }

    public function structure(Request $request): JsonResponse
    {
        return response()->json(
            $this->dashboard->structure($request->user(), $request->integer('structure_id') ?: null)
        );
    }

    public function direction(Request $request): JsonResponse
    {
        return response()->json($this->dashboard->direction($request->user()));
    }
}
