<?php

namespace App\Features\Authentication\Infrastructure\Providers;

use App\Features\Authentication\Domain\Contracts\AuthenticationRepositoryInterface;
use App\Features\Authentication\Domain\Contracts\PasswordResetNotifierInterface;
use App\Features\Authentication\Infrastructure\Notifications\PasswordResetNotifier;
use App\Features\Authentication\Infrastructure\Repositories\AuthenticationRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AuthenticationServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        AuthenticationRepositoryInterface::class => AuthenticationRepository::class,
        PasswordResetNotifierInterface::class => PasswordResetNotifier::class,
    ];

    public function boot(): void
    {
        $this->app->booted(function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(__DIR__.'/../../Http/Routes/Routes.php');
        });

        RateLimiter::for('auth-sign-in', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('auth-password-reset', function (Request $request) {
            return Limit::perHour(3)->by($request->ip());
        });
    }
}
