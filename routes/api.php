<?php

use App\Http\Controllers\Api\V1\MobileMetaController;
use App\Http\Controllers\Api\V1\PairingVerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/meta', MobileMetaController::class)
        ->middleware('throttle:mobile-meta')
        ->name('api.v1.meta');

    Route::post('/pairings/verify', PairingVerificationController::class)
        ->middleware('throttle:mobile-pairing')
        ->name('api.v1.pairings.verify');
});
