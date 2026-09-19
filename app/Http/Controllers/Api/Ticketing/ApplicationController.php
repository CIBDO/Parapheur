<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        $query = Application::query()->orderBy('name');

        if ($q = $request->input('q')) {
            $like = '%'.addcslashes((string) $q, '%_\\').'%';
            $query->where(function ($qq) use ($like) {
                $qq->where('code', 'like', $like)->orWhere('name', 'like', $like);
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json($query->paginate(min((int) $request->input('per_page', 50), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:applications,code',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $app = Application::query()->create($validated);

        return response()->json($app, 201);
    }

    public function show(Request $request, Application $application): JsonResponse
    {
        $this->authorizeView($request);

        return response()->json($application);
    }

    public function update(Request $request, Application $application): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:applications,code,'.$application->id,
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $application->update($validated);

        return response()->json($application);
    }

    private function authorizeView(Request $request): void
    {
        abort_unless(
            $request->user()->can('ticket.view')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless(
            $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );
    }
}
