<?php

use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\MobileCustomerAppointmentController;
use App\Http\Controllers\Api\V1\MobileCustomerBookingController;
use App\Http\Controllers\Api\V1\MobileCustomerFeedbackController;
use App\Http\Controllers\Api\V1\MobileCustomerProfileController;
use App\Http\Controllers\Api\V1\MobileMetaController;
use App\Http\Controllers\Api\V1\MobileReceptionAppointmentController;
use App\Http\Controllers\Api\V1\MobileReceptionCustomerController;
use App\Http\Controllers\Api\V1\MobileReceptionDashboardController;
use App\Http\Controllers\Api\V1\MobileReceptionTransactionController;
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
        Route::patch('/auth/password', [MobileAuthController::class, 'password'])->name('api.v1.auth.password');

        Route::middleware('role:customer')->prefix('customer')->group(function (): void {
            Route::get('/booking-options', [MobileCustomerBookingController::class, 'options'])->name('api.v1.customer.booking.options');
            Route::get('/availability', [MobileCustomerBookingController::class, 'availability'])->name('api.v1.customer.booking.availability');
            Route::post('/appointments', [MobileCustomerBookingController::class, 'store'])->name('api.v1.customer.appointments.store');
            Route::get('/appointments', [MobileCustomerAppointmentController::class, 'index'])->name('api.v1.customer.appointments.index');
            Route::get('/appointments/{appointment}', [MobileCustomerAppointmentController::class, 'show'])->name('api.v1.customer.appointments.show');
            Route::patch('/appointments/{appointment}/cancel', [MobileCustomerAppointmentController::class, 'cancel'])->name('api.v1.customer.appointments.cancel');
            Route::get('/feedback', [MobileCustomerFeedbackController::class, 'index'])->name('api.v1.customer.feedback.index');
            Route::post('/feedback', [MobileCustomerFeedbackController::class, 'store'])->name('api.v1.customer.feedback.store');
            Route::get('/profile', [MobileCustomerProfileController::class, 'show'])->name('api.v1.customer.profile.show');
            Route::patch('/profile', [MobileCustomerProfileController::class, 'update'])->name('api.v1.customer.profile.update');
        });

        Route::middleware('role:receptionist')->prefix('reception')->group(function (): void {
            Route::get('/dashboard', MobileReceptionDashboardController::class)->name('api.v1.reception.dashboard');

            Route::get('/appointment-options', [MobileReceptionAppointmentController::class, 'options'])->name('api.v1.reception.appointments.options');
            Route::get('/available-therapists', [MobileReceptionAppointmentController::class, 'availableTherapists'])->name('api.v1.reception.appointments.therapists');
            Route::get('/appointments', [MobileReceptionAppointmentController::class, 'index'])->name('api.v1.reception.appointments.index');
            Route::post('/appointments', [MobileReceptionAppointmentController::class, 'store'])->name('api.v1.reception.appointments.store');
            Route::get('/appointments/{appointment}', [MobileReceptionAppointmentController::class, 'show'])->name('api.v1.reception.appointments.show');
            Route::patch('/appointments/{appointment}', [MobileReceptionAppointmentController::class, 'update'])->name('api.v1.reception.appointments.update');
            Route::post('/appointments/{appointment}/outcome', [MobileReceptionAppointmentController::class, 'outcome'])->name('api.v1.reception.appointments.outcome');
            Route::post('/appointments/{appointment}/complete', [MobileReceptionAppointmentController::class, 'complete'])->name('api.v1.reception.appointments.complete');

            Route::get('/customers', [MobileReceptionCustomerController::class, 'index'])->name('api.v1.reception.customers.index');
            Route::get('/customers/{customer}', [MobileReceptionCustomerController::class, 'show'])->name('api.v1.reception.customers.show');
            Route::patch('/customers/{customer}', [MobileReceptionCustomerController::class, 'update'])->name('api.v1.reception.customers.update');

            Route::get('/transaction-options', [MobileReceptionTransactionController::class, 'options'])->name('api.v1.reception.transactions.options');
            Route::get('/transactions', [MobileReceptionTransactionController::class, 'index'])->name('api.v1.reception.transactions.index');
            Route::post('/transactions', [MobileReceptionTransactionController::class, 'store'])->name('api.v1.reception.transactions.store');
            Route::get('/transactions/{transaction}', [MobileReceptionTransactionController::class, 'show'])->name('api.v1.reception.transactions.show');
            Route::patch('/transactions/{transaction}', [MobileReceptionTransactionController::class, 'update'])->name('api.v1.reception.transactions.update');
        });
    });
});
