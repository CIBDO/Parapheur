<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP de base (OWASP).
 * CSP assouplie pour ONLYOFFICE ; en local, autorise aussi le serveur Vite HMR.
 * Note : CSP n’accepte pas les hôtes IPv6 `http://[::1]:…` — utiliser 127.0.0.1.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $onlyOfficeUrl = rtrim((string) config('onlyoffice.url', ''), '/');
        $frameSrc = ["'self'"];
        $scriptSrc = ["'self'", "'unsafe-inline'", "'unsafe-eval'"];
        $connectSrc = ["'self'", 'ws:', 'wss:'];
        $imgSrc = ["'self'", 'data:', 'blob:'];
        $styleSrc = ["'self'", "'unsafe-inline'"];
        $fontSrc = ["'self'", 'data:'];

        if ($onlyOfficeUrl !== '') {
            $frameSrc[] = $onlyOfficeUrl;
            $scriptSrc[] = $onlyOfficeUrl;
            $connectSrc[] = $onlyOfficeUrl;
            $imgSrc[] = $onlyOfficeUrl;
        }

        foreach ($this->viteDevOrigins() as $origin) {
            $scriptSrc[] = $origin;
            $connectSrc[] = $origin;
            $styleSrc[] = $origin;
            $fontSrc[] = $origin;
            $imgSrc[] = $origin;
        }

        $csp = implode('; ', [
            "default-src 'self'",
            'frame-src '.implode(' ', array_unique($frameSrc)),
            'script-src '.implode(' ', array_unique($scriptSrc)),
            'style-src '.implode(' ', array_unique($styleSrc)),
            'img-src '.implode(' ', array_unique($imgSrc)),
            'font-src '.implode(' ', array_unique($fontSrc)),
            'connect-src '.implode(' ', array_unique($connectSrc)),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-XSS-Protection', '0');

        if ($request->isSecure() || app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    /**
     * Origines Vite (HMR) en environnement non-prod uniquement.
     *
     * @return list<string>
     */
    private function viteDevOrigins(): array
    {
        if (app()->environment('production')) {
            return [];
        }

        $origins = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'ws://localhost:5173',
            'ws://127.0.0.1:5173',
        ];

        $hotFile = public_path('hot');
        if (File::exists($hotFile)) {
            $hot = trim((string) File::get($hotFile));
            // [::1] n’est pas une source CSP valide → normaliser en 127.0.0.1
            $hot = str_replace(['[::1]', '::1'], '127.0.0.1', $hot);
            $parts = parse_url($hot);
            if (is_array($parts) && ! empty($parts['scheme']) && ! empty($parts['host'])) {
                $host = $parts['host'];
                $port = isset($parts['port']) ? ':'.$parts['port'] : '';
                $httpOrigin = 'http://'.$host.$port;
                $wsOrigin = 'ws://'.$host.$port;
                if (($parts['scheme'] ?? '') === 'https') {
                    $httpOrigin = 'https://'.$host.$port;
                    $wsOrigin = 'wss://'.$host.$port;
                }
                $origins[] = $httpOrigin;
                $origins[] = $wsOrigin;
            }
        }

        return array_values(array_unique(array_filter(
            $origins,
            fn (string $origin) => ! str_contains($origin, '[')
        )));
    }
}
