<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PortalApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PortalAppController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        $query = PortalApp::query()
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->boolean('active_only') || ! $this->canManage($request)) {
            $query->where('is_active', true);
        }

        if ($q = $request->input('q')) {
            $like = '%'.addcslashes((string) $q, '%_\\').'%';
            $query->where(function ($qq) use ($like) {
                $qq->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        return response()->json($query->get());
    }

    public function categories(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        $categories = PortalApp::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->when(! $this->canManage($request), fn ($q) => $q->where('is_active', true))
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values();

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $app = PortalApp::query()->create(array_merge(
            $this->validated($request),
            ['created_by' => $request->user()->id],
        ));

        return response()->json($app, 201);
    }

    public function show(Request $request, PortalApp $portalApp): JsonResponse
    {
        $this->authorizeView($request);

        if (! $portalApp->is_active && ! $this->canManage($request)) {
            abort(404);
        }

        return response()->json($portalApp);
    }

    public function update(Request $request, PortalApp $portalApp): JsonResponse
    {
        $this->authorizeManage($request);

        $portalApp->update($this->validated($request, $portalApp));

        return response()->json($portalApp->fresh());
    }

    public function destroy(Request $request, PortalApp $portalApp): JsonResponse
    {
        $this->authorizeManage($request);

        $portalApp->delete();

        return response()->json(['message' => 'Application supprimée']);
    }

    private function validated(Request $request, ?PortalApp $portalApp = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('portal_apps', 'code')->ignore($portalApp?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'open_in_new_tab' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $data['is_active'] = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : ($portalApp?->is_active ?? true);
        $data['open_in_new_tab'] = array_key_exists('open_in_new_tab', $data)
            ? (bool) $data['open_in_new_tab']
            : ($portalApp?->open_in_new_tab ?? true);
        $data['sort_order'] = $data['sort_order'] ?? ($portalApp?->sort_order ?? 0);
        $data['icon'] = $data['icon'] ?? ($portalApp?->icon ?? 'tabler-apps');
        $data['color'] = $data['color'] ?? ($portalApp?->color ?? 'primary');

        return $data;
    }

    private function authorizeView(Request $request): void
    {
        abort_unless(
            $request->user()->can('portal_app.view')
            || $this->canManage($request),
            403
        );
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->can('portal_app.manage')
            || $request->user()->can('admin.access');
    }
}
