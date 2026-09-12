<?php

namespace App\Services\OnlyOffice;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use Throwable;

/**
 * JWT HS256 pour ONLYOFFICE Document Server (via firebase/php-jwt).
 * Supporte une rotation douce via ONLYOFFICE_JWT_SECRET_PREVIOUS.
 */
class OnlyOfficeJwt
{
    public function encode(array $payload, ?int $ttl = null): string
    {
        $secret = $this->primarySecret();

        $now = time();
        $ttl ??= (int) config('onlyoffice.jwt_ttl', 3600);

        $claims = $payload;
        $claims['iat'] = $payload['iat'] ?? $now;
        $claims['exp'] = $payload['exp'] ?? ($now + $ttl);

        return JWT::encode($claims, $secret, 'HS256');
    }

    public function decode(string $token): array
    {
        $secrets = $this->secrets();
        $lastError = null;

        foreach ($secrets as $secret) {
            try {
                $decoded = JWT::decode($token, new Key($secret, 'HS256'));

                return json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                $lastError = $e;
            }
        }

        throw new InvalidArgumentException(
            'JWT ONLYOFFICE invalide : '.($lastError?->getMessage() ?? 'secret manquant'),
            0,
            $lastError
        );
    }

    public function tokenFromRequestHeader(?string $headerValue): ?string
    {
        if ($headerValue === null || $headerValue === '') {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', trim($headerValue), $matches)) {
            return trim($matches[1]);
        }

        return trim($headerValue);
    }

    /**
     * @return list<string>
     */
    public function secrets(): array
    {
        $primary = trim((string) config('onlyoffice.jwt_secret', ''));
        $previous = trim((string) config('onlyoffice.jwt_secret_previous', ''));

        $secrets = [];
        if ($primary !== '') {
            $secrets[] = $primary;
        }
        if ($previous !== '' && $previous !== $primary) {
            $secrets[] = $previous;
        }

        if ($secrets === []) {
            throw new InvalidArgumentException('ONLYOFFICE_JWT_SECRET manquant.');
        }

        return $secrets;
    }

    public function assertSecretStrength(?string $secret = null): void
    {
        $secret ??= (string) config('onlyoffice.jwt_secret', '');
        if ($secret === '') {
            throw new InvalidArgumentException('ONLYOFFICE_JWT_SECRET manquant.');
        }

        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('ONLYOFFICE_JWT_SECRET doit faire au moins 32 caractères.');
        }

        $weak = [
            'change-me-onlyoffice-jwt-secret-32b',
            'secret',
            'jwt_secret',
            'onlyoffice',
        ];

        if (in_array(strtolower($secret), $weak, true)) {
            throw new InvalidArgumentException('ONLYOFFICE_JWT_SECRET trop faible — générez-en un avec php artisan onlyoffice:generate-jwt-secret.');
        }
    }

    private function primarySecret(): string
    {
        $secret = trim((string) config('onlyoffice.jwt_secret', ''));
        if ($secret === '') {
            throw new InvalidArgumentException('ONLYOFFICE_JWT_SECRET manquant.');
        }

        return $secret;
    }
}
