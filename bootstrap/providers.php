<?php

use App\Core\Main\Providers\HorizonServiceProvider;
use App\Features\Authentication\Main\Providers\AuthenticationServiceProvider;
use App\Features\Expenses\Main\Providers\ExpenseServiceProvider;

return [
    AuthenticationServiceProvider::class,
    ExpenseServiceProvider::class,
    HorizonServiceProvider::class,
];
