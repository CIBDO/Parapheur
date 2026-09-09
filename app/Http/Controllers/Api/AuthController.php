<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
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

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->where('is_active', true)
            ->first();

        // Réponse neutre pour ne pas révéler si l'e-mail existe.
        if ($user) {
            Password::broker()->sendResetLink(
                $request->only('email')
            );
        }

        return response()->json([
            'message' => 'Si un compte est associé à cette adresse, un e-mail de réinitialisation a été envoyé.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $message = match ($status) {
                Password::INVALID_TOKEN => 'Ce lien de réinitialisation est invalide ou a expiré.',
                Password::INVALID_USER => 'Impossible de réinitialiser le mot de passe pour cette adresse.',
                default => 'Réinitialisation impossible. Demandez un nouveau lien.',
            };

            throw ValidationException::withMessages([
                'email' => [$message],
            ]);
        }

        return response()->json([
            'message' => 'Mot de passe réinitialisé. Vous pouvez vous connecter.',
        ]);
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
