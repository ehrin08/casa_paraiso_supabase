<?php

use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\MobileCustomerAppointmentController;
use App\Http\Controllers\Api\V1\MobileCustomerBookingController;
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

    Route::post('/auth/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:mobile-login')
        ->name('api.v1.auth.login');

    Route::middleware(['auth:sanctum', 'active_mobile'])->group(function (): void {
        Route::get('/auth/me', [MobileAuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('api.v1.auth.logout');

        Route::middleware('role:customer')->prefix('customer')->group(function (): void {
            Route::get('/booking-options', [MobileCustomerBookingController::class, 'options'])->name('api.v1.customer.booking.options');
            Route::get('/availability', [MobileCustomerBookingController::class, 'availability'])->name('api.v1.customer.booking.availability');
            Route::post('/appointments', [MobileCustomerBookingController::class, 'store'])->name('api.v1.customer.appointments.store');
            Route::get('/appointments', [MobileCustomerAppointmentController::class, 'index'])->name('api.v1.customer.appointments.index');
            Route::get('/appointments/{appointment}', [MobileCustomerAppointmentController::class, 'show'])->name('api.v1.customer.appointments.show');
            Route::patch('/appointments/{appointment}/cancel', [MobileCustomerAppointmentController::class, 'cancel'])->name('api.v1.customer.appointments.cancel');
        });
    });
});
