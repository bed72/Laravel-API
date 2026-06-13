<?php

namespace App\Features\Authentication\Main\Providers;

use App\Features\Authentication\Domain\Gateways\PasswordResetBrokerInterface;
use App\Features\Authentication\Domain\Gateways\PasswordResetNotifierInterface;
use App\Features\Authentication\Domain\Gateways\TokenIssuerInterface;
use App\Features\Authentication\Domain\Repositories\UserRepositoryInterface;
use App\Features\Authentication\Infrastructure\Gateways\PasswordResetBroker;
use App\Features\Authentication\Infrastructure\Gateways\PasswordResetNotifier;
use App\Features\Authentication\Infrastructure\Gateways\SanctumTokenIssuer;
use App\Features\Authentication\Infrastructure\Repositories\UserRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AuthenticationServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        TokenIssuerInterface::class => SanctumTokenIssuer::class,
        PasswordResetBrokerInterface::class => PasswordResetBroker::class,
        PasswordResetNotifierInterface::class => PasswordResetNotifier::class,
    ];

    public function boot(): void
    {
        $this->app->booted(function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(__DIR__.'/../Routes/Routes.php');
        });

        // Login: limite apertado por conta-alvo (email|ip) + teto por IP (anti credential-stuffing).
        RateLimiter::for('auth-sign-in', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return [
                Limit::perMinute(5)->by($key),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // Cadastro: teto por IP contra criação de contas em massa.
        RateLimiter::for('auth-sign-up', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Reset de senha: por conta-alvo (email|ip) + teto por IP.
        RateLimiter::for('auth-password-reset', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return [
                Limit::perHour(3)->by($key),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });
    }
}
