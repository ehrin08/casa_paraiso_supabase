<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('login', 'auth.login')->name('login');
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

    $googleOnly = fn () => redirect()->route('login')->with('auth_notice', 'Casa Paraiso now uses Google sign-in.');
    Route::get('register', $googleOnly)->name('register');
    Route::get('forgot-password', $googleOnly)->name('password.request');
    Route::get('reset-password/{token}', $googleOnly)->name('password.reset');
    Route::get('confirm-password', $googleOnly)->name('password.confirm');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(['auth', 'active'])
    ->name('logout');
