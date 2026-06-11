<?php

use App\Features\Expenses\Http\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('expenses')->group(function () {
    Route::get('/', [ExpenseController::class, 'index']);
    Route::post('/', [ExpenseController::class, 'store']);
    Route::get('/{id}', [ExpenseController::class, 'show']);
});