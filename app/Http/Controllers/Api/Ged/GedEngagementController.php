<?php

namespace App\Http\Controllers\Api\Ged;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentAccessService;
use App\Services\DocumentEngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GedEngagementController extends Controller
{
    public function __construct(
        private readonly DocumentEngagementService $engagement,
        private readonly DocumentAccessService $access,
    ) {}

    public function favorites(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        return response()->json(
            $this->engagement->favorites($request->user(), (int) $request->integer('per_page', 15))
        );
    }

    public function recent(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        return response()->json([
            'data' => $this->engagement->recent($request->user(), (int) $request->integer('limit', 20)),
        ]);
    }

    public function toggleFavorite(Request $request, Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $favorited = $this->engagement->toggleFavorite($request->user(), $document);

        return response()->json([
            'favorited' => $favorited,
            'message' => $favorited ? 'Ajouté aux favoris.' : 'Retiré des favoris.',
        ]);
    }
}
