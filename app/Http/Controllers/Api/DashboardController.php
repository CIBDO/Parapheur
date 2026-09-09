<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ParapheurService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private readonly ParapheurService $parapheur) {}

    public function dg(): JsonResponse
    {
        return response()->json($this->parapheur->dashboardDg());
    }

    public function direction(Request $request): JsonResponse
    {
        $structureId = $request->user()->structure_id;

        return response()->json([
            'prepared' => DB::table('documents')->where('structure_id', $structureId)->count(),
            'in_validation' => DB::table('documents')->where('structure_id', $structureId)->whereIn('status', ['en_circuit', 'a_viser', 'a_valider'])->count(),
            'sent_dg' => DB::table('documents')->where('structure_id', $structureId)->whereIn('status', ['transmis', 'a_consulter', 'a_valider'])->count(),
            'returned' => DB::table('documents')->where('structure_id', $structureId)->where('status', 'a_corriger')->count(),
            'validated' => DB::table('documents')->where('structure_id', $structureId)->where('status', 'valide')->count(),
            'overdue' => DB::table('documents')->where('structure_id', $structureId)->whereNotNull('due_date')->whereDate('due_date', '<', now())->whereNotIn('status', ['valide', 'archive', 'traite'])->count(),
        ]);
    }
}
