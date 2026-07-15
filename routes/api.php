<?php

use App\Http\Controllers\Api\V1\MobileMetaController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\PairingVerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/meta', MobileMetaController::class)
        ->middleware('throttle:mobile-meta')
        ->name('api.v1.meta');

    Route::post('/pairings/verify', PairingVerificationController::class)
        ->middleware('throttle:mobile-pairing')
        ->name('api.v1.pairings.verify');

    Route::post('/auth/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:mobile-login')
        ->name('api.v1.auth.login');

    Route::middleware(['auth:sanctum', 'active_mobile'])->group(function (): void {
        Route::get('/auth/me', [MobileAuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('api.v1.auth.logout');
    });
});
