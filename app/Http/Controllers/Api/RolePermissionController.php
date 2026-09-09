<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    public function roles(): JsonResponse
    {
        $userCounts = $this->roleUserCounts();

        $roles = Role::query()
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'users_count' => (int) ($userCounts[$role->id] ?? 0),
                'permissions' => $role->permissions->pluck('name')->values(),
                'permission_ids' => $role->permissions->pluck('id')->values(),
            ]);

        return response()->json($roles);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($data['permissions'] ?? []);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json($this->rolePayload($role->fresh('permissions')), 201);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json($this->rolePayload($role->fresh('permissions')));
    }

    public function destroyRole(Role $role): JsonResponse
    {
        if ($role->name === 'Administrateur') {
            return response()->json([
                'message' => 'Le rôle Administrateur ne peut pas être supprimé.',
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
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name', 'created_at']);

        return response()->json($permissions);
    }

    public function storePermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', 'unique:permissions,name', 'regex:/^[a-z0-9_.-]+$/'],
        ]);

        $permission = Permission::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json($permission, 201);
    }

    public function destroyPermission(Permission $permission): JsonResponse
    {
        $protected = [
            'admin.access',
            'documents.create',
            'documents.act',
        ];

        if (in_array($permission->name, $protected, true)) {
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

    private function rolePayload(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'users_count' => $this->countUsersForRole($role->id),
            'permissions' => $role->permissions->pluck('name')->values(),
            'permission_ids' => $role->permissions->pluck('id')->values(),
        ];
    }

    /**
     * Compte les utilisateurs par rôle sans passer par Role::users(),
     * qui échoue avec withCount() (guard_name absent → modèle null).
     *
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
