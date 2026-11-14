<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Productions\ProductionDashboardService;
use App\Services\Productions\ProductionOccurrenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionDashboardController extends Controller
{
    public function __construct(
        private readonly ProductionDashboardService $dashboard,
        private readonly ProductionOccurrenceService $occurrences,
    ) {}

    public function myWork(Request $request): JsonResponse
    {
        // Filet de sécurité si le cron de génération n'a pas tourné
        try {
            $this->occurrences->generateForDate(now()->startOfDay(), $request->user());
        } catch (\Throwable) {
            // ne bloque pas le tableau de bord
        }

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
