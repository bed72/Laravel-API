<?php

namespace App\Features\Expenses\Infrastructure\Providers;

use App\Features\Expenses\Domain\Contracts\ExpenseRepositoryInterface;
use App\Features\Expenses\Infrastructure\Repositories\ExpenseRepository;
use Illuminate\Support\ServiceProvider;
use Route;

class ExpenseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ExpenseRepositoryInterface::class,
            ExpenseRepository::class,
        );
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(
                base_path(
                    'app/Features/Expenses/Http/Routes/Routes.php'
                )
            );
    }
}