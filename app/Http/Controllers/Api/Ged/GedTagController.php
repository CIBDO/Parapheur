<?php

namespace App\Http\Controllers\Api\Ged;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GedTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        $q = trim((string) $request->query('q', ''));

        $query = DocumentTag::query()->orderByDesc('usage_count')->orderBy('name');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', '%'.$q.'%')
                    ->orWhere('slug', 'like', '%'.$q.'%');
            });
        }

        return response()->json([
            'data' => $query->limit(30)->get(),
        ]);
    }
}
