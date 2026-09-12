<?php

namespace App\Services\Identity;

use App\Contracts\IdentityProvider;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LocalIdentityProvider implements IdentityProvider
{
    public function attempt(array $credentials): ?User
    {
        $email = (string) ($credentials['email'] ?? '');
        $password = (string) ($credentials['password'] ?? '');

        if ($email === '' || $password === '') {
            return null;
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->is_active || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    public function driver(): string
    {
        return 'local';
    }
}
