<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    public function roles(): JsonResponse
    {
        $userCounts = $this->roleUserCounts();

        $roles = Role::query()
            ->with('permissions:id,name,label,module')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->rolePayload($role, (int) ($userCounts[$role->id] ?? 0)));

        return response()->json($roles);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', 'unique:roles,name'],
            'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', 'unique:roles,code'],
            'home_route' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $permissions = $data['permissions'] ?? [];
        $this->assertCanAssignPermissions($request->user(), $permissions);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'code' => $data['code'] ?? Str::slug($data['name'], '_'),
            'is_system' => false,
            'home_route' => $data['home_route'] ?? '/parapheur',
        ]);

        $role->syncPermissions($permissions);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json($this->rolePayload($role->fresh('permissions')), 201);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->ignore($role->id)],
            'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'code')->ignore($role->id)],
            'home_route' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $permissions = $data['permissions'] ?? [];
        $this->assertCanAssignPermissions($request->user(), $permissions);

        if ($role->is_system) {
            // Les rôles système gardent leur code ; le nom affiché peut évoluer.
            $role->update([
                'name' => $data['name'],
                'home_route' => $data['home_route'] ?? $role->home_route,
            ]);
        } else {
            $role->update([
                'name' => $data['name'],
                'code' => $data['code'] ?? $role->code,
                'home_route' => $data['home_route'] ?? $role->home_route,
            ]);
        }

        $role->syncPermissions($permissions);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json($this->rolePayload($role->fresh('permissions')));
    }

    public function destroyRole(Role $role): JsonResponse
    {
        if ($role->is_system || $role->code === 'admin') {
            return response()->json([
                'message' => 'Ce rôle système ne peut pas être supprimé.',
            ], 422);
        }

        if ($this->countUsersForRole($role->id) > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer un rôle encore attribué à des utilisateurs.',
            ], 422);
        }

        $role->syncPermissions([]);
        $role->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json(['message' => 'Rôle supprimé']);
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('module')
            ->orderBy('label')
            ->orderBy('name')
            ->get(['id', 'name', 'label', 'module', 'description', 'is_system', 'guard_name', 'created_at'])
            ->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'label' => $permission->label ?: PermissionCatalog::defaultLabel($permission->name),
                'module' => $permission->module,
                'module_label' => $permission->module
                    ? PermissionCatalog::moduleLabel($permission->module)
                    : null,
                'description' => $permission->description,
                'is_system' => (bool) $permission->is_system,
                'guard_name' => $permission->guard_name,
                'created_at' => $permission->created_at,
            ]);

        return response()->json($permissions);
    }

    public function storePermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', 'unique:permissions,name', 'regex:/^[a-z0-9_.-]+$/'],
            'label' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $module = $data['module'] ?? Str::before($data['name'], '.') ?: 'autre';

        $permission = Permission::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'label' => $data['label'] ?? PermissionCatalog::defaultLabel($data['name']),
            'module' => $module,
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'id' => $permission->id,
            'name' => $permission->name,
            'label' => $permission->label,
            'module' => $permission->module,
            'module_label' => PermissionCatalog::moduleLabel($permission->module),
            'description' => $permission->description,
            'is_system' => false,
            'guard_name' => $permission->guard_name,
        ], 201);
    }

    public function destroyPermission(Permission $permission): JsonResponse
    {
        if ($permission->is_system || in_array($permission->name, PermissionCatalog::systemPermissionNames(), true)) {
            return response()->json([
                'message' => 'Cette permission système ne peut pas être supprimée.',
            ], 422);
        }

        DB::table(config('permission.table_names.role_has_permissions'))
            ->where('permission_id', $permission->id)
            ->delete();

        $permission->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json(['message' => 'Permission supprimée']);
    }

    /**
     * Empêche l’élévation vers des permissions système hors compte Administrateur.
     *
     * @param  list<string>  $permissions
     */
    private function assertCanAssignPermissions(?User $actor, array $permissions): void
    {
        $protected = array_values(array_intersect($permissions, [
            'admin.access',
        ]));

        if ($protected === []) {
            return;
        }

        if (! $actor || ! $actor->can('admin.access') || ! $actor->hasRoleWithCode('admin')) {
            abort(403, 'Seuls les administrateurs système peuvent attribuer admin.access.');
        }
    }

    private function rolePayload(Role $role, ?int $usersCount = null): array
    {
        $role->loadMissing('permissions:id,name,label,module');

        return [
            'id' => $role->id,
            'name' => $role->name,
            'code' => $role->code,
            'is_system' => (bool) $role->is_system,
            'home_route' => $role->home_route,
            'guard_name' => $role->guard_name,
            'users_count' => $usersCount ?? $this->countUsersForRole($role->id),
            'permissions' => $role->permissions->pluck('name')->values(),
            'permission_ids' => $role->permissions->pluck('id')->values(),
            'permission_labels' => $role->permissions
                ->mapWithKeys(fn (Permission $p) => [
                    $p->name => $p->label ?: PermissionCatalog::defaultLabel($p->name),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function roleUserCounts(): array
    {
        return DB::table(config('permission.table_names.model_has_roles'))
            ->select('role_id', DB::raw('COUNT(*) as aggregate'))
            ->where('model_type', User::class)
            ->groupBy('role_id')
            ->pluck('aggregate', 'role_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function countUsersForRole(int $roleId): int
    {
        return (int) DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $roleId)
            ->where('model_type', User::class)
            ->count();
    }
}
