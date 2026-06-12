<?php

use App\Features\Expenses\Infrastructure\Providers\ExpenseServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    ExpenseServiceProvider::class,
    HorizonServiceProvider::class,
];
