<?php

namespace App\Providers;

use App\Contracts\DocumentPreviewDriver;
use App\Contracts\IdentityProvider;
use App\Contracts\Search\SearchEngineInterface;
use App\Models\Appointment;
use App\Models\Document;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\DocumentPolicy;
use App\Services\Identity\LdapIdentityProvider;
use App\Services\Identity\LocalIdentityProvider;
use App\Services\Identity\SsoIdentityProvider;
use App\Services\OnlyOffice\OnlyOfficeJwt;
use App\Services\Preview\NativeDocumentPreviewDriver;
use App\Services\Preview\OnlyOfficeDocumentPreviewDriver;
use App\Services\Search\DatabaseSearchEngine;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DocumentPreviewDriver::class, function ($app) {
            if (config('onlyoffice.enabled') && (string) config('onlyoffice.jwt_secret') !== '') {
                return $app->make(OnlyOfficeDocumentPreviewDriver::class);
            }

            return $app->make(NativeDocumentPreviewDriver::class);
        });

        $this->app->bind(SearchEngineInterface::class, DatabaseSearchEngine::class);

        $this->app->singleton(IdentityProvider::class, function ($app) {
            return match ((string) config('identity.driver', 'local')) {
                'ldap' => $app->make(LdapIdentityProvider::class),
                'sso' => $app->make(SsoIdentityProvider::class),
                default => $app->make(LocalIdentityProvider::class),
            };
        });
    }

    public function boot(): void
    {
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);

        if (config('onlyoffice.enabled')) {
            try {
                $this->app->make(OnlyOfficeJwt::class)->assertSecretStrength();
            } catch (InvalidArgumentException $e) {
                // En local on logue ; en prod on bloque le boot si ONLYOFFICE_ENABLED.
                if (app()->environment('production')) {
                    throw $e;
                }
                Log::warning('ONLYOFFICE JWT : '.$e->getMessage());
            }
        }

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(3)->by($email.'|'.$request->ip()),
                Limit::perMinute(10)->by($request->ip()),
            ];
        });

        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return url('/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->email,
            ]));
        });

        ResetPassword::toMailUsing(function (User $user, string $token) {
            $url = url('/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->email,
            ]));

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('[e-Parapheur] Réinitialisation du mot de passe')
                ->greeting('Bonjour '.$user->name.',')
                ->line('Vous recevez cet e-mail car une demande de réinitialisation de mot de passe a été effectuée pour votre compte.')
                ->action('Choisir un nouveau mot de passe', $url)
                ->line('Ce lien expire dans '.config('auth.passwords.users.expire', 60).' minutes.')
                ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez simplement cet e-mail.');
        });
    }
}
