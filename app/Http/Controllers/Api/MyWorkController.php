<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tasks\WorkAggregationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyWorkController extends Controller
{
    public function __construct(
        private readonly WorkAggregationService $work,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 10);

        return response()->json($this->work->forUser($request->user(), max(1, min($limit, 50))));
    }
}
