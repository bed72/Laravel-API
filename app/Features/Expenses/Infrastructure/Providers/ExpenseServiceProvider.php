<?php

namespace App\Features\Expenses\Infrastructure\Providers;

use App\Features\Expenses\Domain\Contracts\ExpenseRepositoryInterface;
use App\Features\Expenses\Infrastructure\Repositories\ExpenseRepository;
use App\Support\Providers\FeatureServiceProvider;

class ExpenseServiceProvider extends FeatureServiceProvider
{
    public array $bindings = [
        ExpenseRepositoryInterface::class => ExpenseRepository::class,
    ];

    public function boot(): void
    {
        $this->loadFeatureRoutes(__DIR__.'/../../Http/Routes/Routes.php');
    }
}
