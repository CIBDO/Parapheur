<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'accessToken' => $token,
            'userData' => $this->userPayload($user),
            'userAbilityRules' => $user->abilityRules(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load(['structure', 'roles']);

        return response()->json([
            'userData' => $this->userPayload($user),
            'userAbilityRules' => $user->abilityRules(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnecté']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'fullName' => $user->name,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'username' => strstr($user->email, '@', true) ?: $user->email,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first() ?? 'agent',
            'roles' => $user->getRoleNames(),
            'structure' => $user->structure?->only(['id', 'code', 'name']),
            'position_title' => $user->position_title,
            'avatar' => null,
        ];
    }
}
