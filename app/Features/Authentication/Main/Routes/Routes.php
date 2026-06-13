<?php

use App\Features\Authentication\Presentation\Controllers\AuthenticationController;
use App\Features\Authentication\Presentation\Middleware\RotateToken;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/sign-up', [AuthenticationController::class, 'signUp'])
        ->middleware('throttle:auth-sign-up');
    Route::post('/sign-in', [AuthenticationController::class, 'signIn'])
        ->middleware('throttle:auth-sign-in');
    Route::post('/password/request', [AuthenticationController::class, 'requestPasswordReset'])
        ->middleware('throttle:auth-password-reset');
    Route::post('/password/reset', [AuthenticationController::class, 'resetPassword'])
        ->middleware('throttle:auth-password-reset');

    Route::middleware(['auth:sanctum', RotateToken::class])->group(function () {
        Route::post('/sign-out', [AuthenticationController::class, 'signOut']);
        Route::post('/sign-out-all', [AuthenticationController::class, 'logOut']);
    });
});
