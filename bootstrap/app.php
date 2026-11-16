<?php

use App\Http\Middleware\AttachAuthTokenFromCookie;
use App\Http\Middleware\EnsureNotIdle;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->throttleApi('api');
        // Token Sanctum en cookie HttpOnly : ne pas chiffrer (sinon illisible hors middleware web).
        $middleware->encryptCookies(except: [
            AttachAuthTokenFromCookie::COOKIE_NAME,
        ]);
        // Avant auth:sanctum — toujours, pas seulement si la requête est « stateful ».
        $middleware->prependToGroup('api', AttachAuthTokenFromCookie::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'password.changed' => EnsurePasswordChanged::class,
            'auth.idle' => EnsureNotIdle::class,
            'mail.context' => \App\Http\Middleware\EnsureMailOrganizationalContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
