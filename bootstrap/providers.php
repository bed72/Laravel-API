<?php

use App\Core\Providers\HorizonServiceProvider;
use App\Features\Expenses\Infrastructure\Providers\ExpenseServiceProvider;

return [
    ExpenseServiceProvider::class,
    HorizonServiceProvider::class,
];
