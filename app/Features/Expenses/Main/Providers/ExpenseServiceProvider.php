<?php

namespace App\Features\Expenses\Main\Providers;

use App\Features\Expenses\Domain\Repositories\ExpenseRepositoryInterface;
use App\Features\Expenses\Infrastructure\Repositories\ExpenseRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ExpenseServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ExpenseRepositoryInterface::class => ExpenseRepository::class,
    ];

    public function boot(): void
    {
        $this->app->booted(function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(__DIR__.'/../Routes/Routes.php');
        });
    }
}
