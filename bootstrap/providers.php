<?php

use App\Core\Providers\HorizonServiceProvider;
use App\Features\Authentication\Infrastructure\Providers\AuthenticationServiceProvider;
use App\Features\Expenses\Infrastructure\Providers\ExpenseServiceProvider;

return [
    AuthenticationServiceProvider::class,
    ExpenseServiceProvider::class,
    HorizonServiceProvider::class,
];
