<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Models\Correspondent;
use App\Models\CorrespondenceAssignmentAction;
use App\Models\CorrespondenceCategory;
use App\Models\CorrespondenceChannel;
use App\Models\CorrespondenceQualification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailMetaController extends Controller
{
    public function channels(): JsonResponse
    {
        $channels = CorrespondenceChannel::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json($channels);
    }

    public function categories(): JsonResponse
    {
        $categories = CorrespondenceCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }

    public function qualifications(): JsonResponse
    {
        $qualifications = CorrespondenceQualification::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json($qualifications);
    }

    public function actions(): JsonResponse
    {
        $actions = CorrespondenceAssignmentAction::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json($actions);
    }

    public function correspondents(Request $request): JsonResponse
    {
        $query = Correspondent::query()->where('is_active', true);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('organization', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $correspondents = $query->orderBy('name')->limit(50)->get();

        return response()->json($correspondents);
    }
}
