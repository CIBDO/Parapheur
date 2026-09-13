<?php

namespace App\Http\Controllers\Api\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\WorkspaceQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceStorageController extends Controller
{
    public function __construct(
        private readonly WorkspaceQuotaService $quotas,
    ) {}

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        return response()->json($this->quotas->storagePayload($workspace));
    }
}
