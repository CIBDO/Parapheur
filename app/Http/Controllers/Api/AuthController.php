<?php

namespace App\Http\Controllers\Api;

use App\Contracts\IdentityProvider;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\Civilities;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly IdentityProvider $identity,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->identity->attempt($credentials);

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        $user->load(['structure', 'roles']);
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

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', Rule::in(Civilities::values())],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'position_title' => ['nullable', 'string', 'max:150'],
        ], [
            'first_name.required' => 'Le prénom est obligatoire.',
            'last_name.required' => 'Le nom est obligatoire.',
            'email.required' => 'L’adresse e-mail est obligatoire.',
            'email.email' => 'L’adresse e-mail n’est pas valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
            'title.in' => 'La civilité sélectionnée est invalide.',
        ]);

        $composedName = trim($data['first_name'].' '.$data['last_name']);

        $user->fill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => $composedName !== '' ? $composedName : $user->name,
            'title' => $data['title'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'],
            'position_title' => $data['position_title'] ?? null,
        ])->save();

        $user->load(['structure', 'roles']);

        return response()->json([
            'message' => 'Profil mis à jour.',
            'userData' => $this->userPayload($user),
            'userAbilityRules' => $user->abilityRules(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnecté']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mot de passe actuel incorrect.'],
            ]);
        }

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Le nouveau mot de passe doit être différent de l’actuel.'],
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()
            ->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->delete();

        return response()->json([
            'message' => 'Mot de passe mis à jour.',
            'userData' => $this->userPayload($user->fresh(['structure', 'roles'])),
        ]);
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
                    'must_change_password' => false,
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
            'title' => $user->title,
            'phone' => $user->phone,
            'username' => strstr($user->email, '@', true) ?: $user->email,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first() ?? 'agent',
            'roles' => $user->getRoleNames(),
            'structure' => $user->structure?->only(['id', 'code', 'name']),
            'position_title' => $user->position_title,
            'mustChangePassword' => (bool) $user->must_change_password,
            'avatar' => null,
        ];
    }
}
