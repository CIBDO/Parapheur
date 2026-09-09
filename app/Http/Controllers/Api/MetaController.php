<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaController extends Controller
{
    public function documentTypes(): JsonResponse
    {
        return response()->json(
            DocumentType::query()->where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    public function structures(): JsonResponse
    {
        return response()->json(
            Structure::query()->with('type')->where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::query()->with(['structure', 'roles'])->where('is_active', true);

        if ($request->filled('structure_id')) {
            $query->where('structure_id', $request->integer('structure_id'));
        }

        $users = $query->orderBy('name')->get(['id', 'name', 'email', 'structure_id', 'position_title']);

        return response()->json($users->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'structure_id' => $user->structure_id,
            'position_title' => $user->position_title,
            'role' => $user->getRoleNames()->first() ?? 'agent',
            'roles' => $user->getRoleNames()->values(),
            'structure' => $user->structure?->only(['id', 'code', 'name']),
        ]));
    }
}
