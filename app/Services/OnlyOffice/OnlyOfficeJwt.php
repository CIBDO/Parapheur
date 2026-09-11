<?php

namespace App\Services\OnlyOffice;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use Throwable;

/**
 * JWT HS256 pour ONLYOFFICE Document Server (via firebase/php-jwt).
 */
class OnlyOfficeJwt
{
    public function encode(array $payload, ?int $ttl = null): string
    {
        $secret = (string) config('onlyoffice.jwt_secret');
        if ($secret === '') {
            throw new InvalidArgumentException('ONLYOFFICE_JWT_SECRET manquant.');
        }

        $now = time();
        $ttl ??= (int) config('onlyoffice.jwt_ttl', 3600);

        $claims = $payload;
        $claims['iat'] = $payload['iat'] ?? $now;
        $claims['exp'] = $payload['exp'] ?? ($now + $ttl);

        return JWT::encode($claims, $secret, 'HS256');
    }

    public function decode(string $token): array
    {
        $secret = (string) config('onlyoffice.jwt_secret');
        if ($secret === '') {
            throw new InvalidArgumentException('ONLYOFFICE_JWT_SECRET manquant.');
        }

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (Throwable $e) {
            throw new InvalidArgumentException('JWT ONLYOFFICE invalide : '.$e->getMessage(), 0, $e);
        }

        return json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
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
}
