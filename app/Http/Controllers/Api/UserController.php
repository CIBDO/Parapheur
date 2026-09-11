<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\UserAccountCreatedNotification;
use App\Support\Civilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['structure:id,code,name', 'roles:id,name'])
            ->orderBy('name');

        if ($request->filled('structure_id')) {
            $query->where('structure_id', $request->integer('structure_id'));
        }

        if ($request->filled('role')) {
            $query->role($request->string('role')->toString());
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('position_title', 'like', $term);
            });
        }

        return response()->json(
            $query->get()->map(fn (User $user) => $this->payload($user))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $role = $data['role'];
        unset($data['role'], $data['password']);

        $plainPassword = Str::password(12);

        $data['name'] = $this->resolveName($data);
        $data['is_active'] = $data['is_active'] ?? true;
        $data['password'] = $plainPassword;
        $data['must_change_password'] = true;

        $user = DB::transaction(function () use ($data, $role, $plainPassword) {
            $user = User::query()->create($data);
            $user->syncRoles([$role]);
            $user->notify(new UserAccountCreatedNotification($plainPassword));

            return $user;
        });

        $user->load(['structure:id,code,name', 'roles:id,name']);

        return response()->json([
            ...$this->payload($user),
            'credentials_sent_by_email' => true,
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['structure:id,code,name', 'roles:id,name']);

        return response()->json($this->payload($user));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $this->validated($request, $user);
        $role = $data['role'] ?? null;
        unset($data['role']);

        if (empty($data['password'])) {
            unset($data['password']);
        }
        else {
            $data['must_change_password'] = true;
        }

        $data['name'] = $this->resolveName($data, $user);

        // Empêcher l'admin de se désactiver lui-même
        if ($request->user()?->id === $user->id && array_key_exists('is_active', $data) && ! $data['is_active']) {
            return response()->json([
                'message' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ], 422);
        }

        $user->update($data);

        if ($role) {
            $user->syncRoles([$role]);
        }

        $user->load(['structure:id,code,name', 'roles:id,name']);

        return response()->json($this->payload($user));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->id === $user->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 422);
        }

        if ($user->authoredDocuments()->exists() || $user->transmissionsReceived()->exists()) {
            $user->update(['is_active' => false]);

            return response()->json([
                'message' => 'Compte désactivé (historique documentaire présent).',
                'deactivated' => true,
            ]);
        }

        $user->tokens()->delete();
        $user->syncRoles([]);
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé']);
    }

    public function roles(): JsonResponse
    {
        return response()->json(
            Role::query()->orderBy('name')->get(['id', 'name'])
        );
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $rules = [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', Rule::in(Civilities::values())],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'position_title' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ];

        // Création : mot de passe généré côté serveur. Édition : reset optionnel.
        if ($user) {
            $rules['password'] = ['nullable', 'string', Password::defaults()];
        }

        return $request->validate($rules);
    }

    private function resolveName(array $data, ?User $user = null): string
    {
        if (! empty($data['name'])) {
            return $data['name'];
        }

        $first = $data['first_name'] ?? $user?->first_name;
        $last = $data['last_name'] ?? $user?->last_name;
        $composed = trim(implode(' ', array_filter([$first, $last])));

        if ($composed !== '') {
            return $composed;
        }

        return $user?->name ?? strstr($data['email'], '@', true) ?: $data['email'];
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'title' => $user->title,
            'phone' => $user->phone,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'must_change_password' => (bool) $user->must_change_password,
            'structure_id' => $user->structure_id,
            'position_title' => $user->position_title,
            'role' => $user->getRoleNames()->first(),
            'roles' => $user->getRoleNames()->values(),
            'structure' => $user->structure?->only(['id', 'code', 'name']),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
