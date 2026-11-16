<?php

namespace App\Http\Controllers\Api;

use App\Contracts\IdentityProvider;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mfa\TotpService;
use App\Support\AuthTokenCookie;
use App\Support\Civilities;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        private readonly TotpService $totp,
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

        if ($user->mfa_enabled && filled($user->mfa_secret)) {
            $challenge = Str::random(64);
            Cache::put($this->mfaChallengeKey($challenge), $user->id, now()->addMinutes(5));

            return response()->json([
                'requiresMfa' => true,
                'mfaChallenge' => $challenge,
                'userData' => [
                    'email' => $user->email,
                    'fullName' => $user->name,
                ],
            ]);
        }

        return $this->issueSession($user);
    }

    public function verifyMfa(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mfa_challenge' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $userId = Cache::pull($this->mfaChallengeKey($data['mfa_challenge']));
        if (! $userId) {
            throw ValidationException::withMessages([
                'code' => ['Défi MFA expiré. Reconnectez-vous.'],
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->with(['structure', 'roles'])->find($userId);
        if (! $user || ! $user->mfa_enabled || ! $this->totp->verify((string) $user->mfa_secret, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => ['Code MFA invalide.'],
            ]);
        }

        return $this->issueSession($user);
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

        return AuthTokenCookie::clear(
            response()->json(['message' => 'Déconnecté'])
        );
    }

    public function mfaSetup(Request $request): JsonResponse
    {
        abort_unless((bool) config('auth.mfa_enabled', false), 503, 'MFA désactivé.');

        /** @var User $user */
        $user = $request->user();
        $secret = $this->totp->generateSecret();
        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_enabled' => false,
            'mfa_confirmed_at' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $this->totp->provisioningUri($secret, $user->email, (string) config('app.name', 'E-Tresor')),
        ]);
    }

    public function mfaConfirm(Request $request): JsonResponse
    {
        abort_unless((bool) config('auth.mfa_enabled', false), 503, 'MFA désactivé.');

        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        if (! filled($user->mfa_secret) || ! $this->totp->verify((string) $user->mfa_secret, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => ['Code MFA invalide.'],
            ]);
        }

        $user->forceFill([
            'mfa_enabled' => true,
            'mfa_confirmed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'MFA activé.',
            'userData' => $this->userPayload($user->fresh(['structure', 'roles'])),
        ]);
    }

    public function mfaDisable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['nullable', 'string', 'size:6'],
        ]);

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Mot de passe incorrect.'],
            ]);
        }

        if ($user->mfa_enabled && filled($user->mfa_secret)) {
            $code = $data['code'] ?? '';
            if ($code === '' || ! $this->totp->verify((string) $user->mfa_secret, $code)) {
                throw ValidationException::withMessages([
                    'code' => ['Code MFA requis pour désactiver.'],
                ]);
            }
        }

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_enabled' => false,
            'mfa_confirmed_at' => null,
        ])->save();

        return response()->json([
            'message' => 'MFA désactivé.',
            'userData' => $this->userPayload($user->fresh(['structure', 'roles'])),
        ]);
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

    private function issueSession(User $user): JsonResponse
    {
        $token = $user->createToken('spa')->plainTextToken;

        // Le token n’est plus exposé au JS : cookie HttpOnly uniquement.
        return AuthTokenCookie::attach(
            response()->json([
                'authenticated' => true,
                'userData' => $this->userPayload($user),
                'userAbilityRules' => $user->abilityRules(),
            ]),
            $token
        );
    }

    private function mfaChallengeKey(string $challenge): string
    {
        return 'auth:mfa:challenge:'.$challenge;
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
            'roleCode' => $user->primaryRole()?->code,
            'roles' => $user->getRoleNames(),
            'homeRoute' => $user->homeRoute(),
            'structure' => $user->structure?->only(['id', 'code', 'name']),
            'mailContext' => app(\App\Services\Organization\OrganizationalContextResolver::class)->present($user),
            'position_title' => $user->position_title,
            'mustChangePassword' => (bool) $user->must_change_password,
            'mfaEnabled' => (bool) $user->mfa_enabled,
            'avatar' => null,
        ];
    }
}
