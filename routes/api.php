<?php

use App\Http\Controllers\Admin\WeeklyRosterController as AdminWeeklyRosterController;
use App\Http\Controllers\Api\V1\MobileAdminCommissionController;
use App\Http\Controllers\Api\V1\MobileAdminDashboardController;
use App\Http\Controllers\Api\V1\MobileAdminFeedbackController;
use App\Http\Controllers\Api\V1\MobileAdminPromotionController;
use App\Http\Controllers\Api\V1\MobileAdminReportController;
use App\Http\Controllers\Api\V1\MobileAdminScheduleController;
use App\Http\Controllers\Api\V1\MobileAdminServiceController;
use App\Http\Controllers\Api\V1\MobileAdminSettingController;
use App\Http\Controllers\Api\V1\MobileAdminStaffController;
use App\Http\Controllers\Api\V1\MobileAdminUserController;
use App\Http\Controllers\Api\V1\MobileAttendanceController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\MobileCustomerAppointmentController;
use App\Http\Controllers\Api\V1\MobileCustomerBookingController;
use App\Http\Controllers\Api\V1\MobileCustomerFeedbackController;
use App\Http\Controllers\Api\V1\MobileCustomerProfileController;
use App\Http\Controllers\Api\V1\MobileDemoApkController;
use App\Http\Controllers\Api\V1\MobileGoogleAuthController;
use App\Http\Controllers\Api\V1\MobileMetaController;
use App\Http\Controllers\Api\V1\MobilePublicBusinessProfileController;
use App\Http\Controllers\Api\V1\MobileReceptionAppointmentController;
use App\Http\Controllers\Api\V1\MobileReceptionCustomerController;
use App\Http\Controllers\Api\V1\MobileReceptionDashboardController;
use App\Http\Controllers\Api\V1\MobileReceptionTransactionController;
use App\Http\Controllers\Api\V1\MobileStaffAppointmentController;
use App\Http\Controllers\Api\V1\MobileStaffCommissionController;
use App\Http\Controllers\Api\V1\MobileStaffCustomerController;
use App\Http\Controllers\Api\V1\MobileStaffDashboardController;
use App\Http\Controllers\Api\V1\MobileStaffFeedbackController;
use App\Http\Controllers\Api\V1\MobileStaffTransactionController;
use App\Http\Middleware\CacheMobileReadResponse;
use App\Http\Middleware\InvalidateMobileReadCache;
use App\Http\Middleware\MeasureApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(MeasureApiRequest::class)->group(function (): void {
    Route::get('/demo/Casa-Paraiso-Mobile.apk', MobileDemoApkController::class)
        ->middleware('throttle:6,1')
        ->name('api.v1.demo.apk');

    Route::get('/meta', MobileMetaController::class)
        ->middleware('throttle:mobile-meta')
        ->name('api.v1.meta');

    Route::get('/public/business-profile', MobilePublicBusinessProfileController::class)
        ->middleware('throttle:60,1')
        ->name('api.v1.public.business-profile');

    Route::post('/auth/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:mobile-login')
        ->name('api.v1.auth.login');

    Route::get('/auth/google/redirect', [MobileGoogleAuthController::class, 'redirect'])
        ->middleware('throttle:mobile-google')
        ->name('api.v1.auth.google.redirect');

    Route::post('/auth/google/exchange', [MobileGoogleAuthController::class, 'exchange'])
        ->middleware('throttle:mobile-google')
        ->name('api.v1.auth.google.exchange');

    Route::middleware(['auth:sanctum', 'active_mobile', InvalidateMobileReadCache::class, CacheMobileReadResponse::class])->group(function (): void {
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

        Route::middleware('role:staff')->prefix('staff')->group(function (): void {
            Route::get('/attendance', [MobileAttendanceController::class, 'mine'])->name('api.v1.staff.attendance.mine');
            Route::post('/attendance/scans', [MobileAttendanceController::class, 'scan'])->name('api.v1.staff.attendance.scan');
            Route::get('/dashboard', MobileStaffDashboardController::class)->name('api.v1.staff.dashboard');
            Route::get('/appointments', [MobileStaffAppointmentController::class, 'index'])->name('api.v1.staff.appointments.index');
            Route::get('/appointments/{appointment}', [MobileStaffAppointmentController::class, 'show'])->name('api.v1.staff.appointments.show');
            Route::post('/appointments/{appointment}/outcome', [MobileStaffAppointmentController::class, 'outcome'])->name('api.v1.staff.appointments.outcome');
            Route::post('/appointments/{appointment}/complete', [MobileStaffAppointmentController::class, 'complete'])->name('api.v1.staff.appointments.complete');
            Route::get('/customers', [MobileStaffCustomerController::class, 'index'])->name('api.v1.staff.customers.index');
            Route::get('/customers/{customer}', [MobileStaffCustomerController::class, 'show'])->name('api.v1.staff.customers.show');
            Route::get('/transactions', [MobileStaffTransactionController::class, 'index'])->name('api.v1.staff.transactions.index');
            Route::get('/transactions/{transaction}', [MobileStaffTransactionController::class, 'show'])->name('api.v1.staff.transactions.show');
            Route::get('/feedback', [MobileStaffFeedbackController::class, 'index'])->name('api.v1.staff.feedback.index');
            Route::get('/feedback/{feedback}', [MobileStaffFeedbackController::class, 'show'])->name('api.v1.staff.feedback.show');
            Route::get('/commissions', [MobileStaffCommissionController::class, 'index'])->name('api.v1.staff.commissions.index');
            Route::get('/commissions/{commission}', [MobileStaffCommissionController::class, 'show'])->name('api.v1.staff.commissions.show');
        });

        Route::middleware('role:super_admin,admin')->prefix('admin')->group(function (): void {
            Route::get('/attendance', [MobileAttendanceController::class, 'index'])->name('api.v1.admin.attendance.index');
            Route::patch('/attendance/{attendance}/correct', [MobileAttendanceController::class, 'correct'])->name('api.v1.admin.attendance.correct');
            Route::get('/dashboard', MobileAdminDashboardController::class)->name('api.v1.admin.dashboard');

            Route::get('/appointments', [MobileReceptionAppointmentController::class, 'index'])->name('api.v1.admin.appointments.index');
            Route::get('/appointments/options', [MobileReceptionAppointmentController::class, 'options'])->name('api.v1.admin.appointments.options');
            Route::get('/appointments/available-therapists', [MobileReceptionAppointmentController::class, 'availableTherapists'])->name('api.v1.admin.appointments.available-therapists');
            Route::post('/appointments', [MobileReceptionAppointmentController::class, 'store'])->name('api.v1.admin.appointments.store');
            Route::get('/appointments/{appointment}', [MobileReceptionAppointmentController::class, 'show'])->name('api.v1.admin.appointments.show');
            Route::patch('/appointments/{appointment}', [MobileReceptionAppointmentController::class, 'update'])->name('api.v1.admin.appointments.update');
            Route::post('/appointments/{appointment}/outcome', [MobileReceptionAppointmentController::class, 'outcome'])->name('api.v1.admin.appointments.outcome');
            Route::post('/appointments/{appointment}/complete', [MobileReceptionAppointmentController::class, 'complete'])->name('api.v1.admin.appointments.complete');

            Route::get('/customers', [MobileReceptionCustomerController::class, 'index'])->name('api.v1.admin.customers.index');
            Route::get('/customers/{customer}', [MobileReceptionCustomerController::class, 'show'])->name('api.v1.admin.customers.show');
            Route::patch('/customers/{customer}', [MobileReceptionCustomerController::class, 'update'])->name('api.v1.admin.customers.update');
            Route::get('/transactions', [MobileReceptionTransactionController::class, 'index'])->name('api.v1.admin.transactions.index');
            Route::get('/transactions/options', [MobileReceptionTransactionController::class, 'options'])->name('api.v1.admin.transactions.options');
            Route::post('/transactions', [MobileReceptionTransactionController::class, 'store'])->name('api.v1.admin.transactions.store');
            Route::get('/transactions/{transaction}', [MobileReceptionTransactionController::class, 'show'])->name('api.v1.admin.transactions.show');
            Route::patch('/transactions/{transaction}', [MobileReceptionTransactionController::class, 'update'])->name('api.v1.admin.transactions.update');

            Route::get('/services', [MobileAdminServiceController::class, 'index'])->name('api.v1.admin.services.index');
            Route::post('/services', [MobileAdminServiceController::class, 'store'])->name('api.v1.admin.services.store');
            Route::get('/services/{service}', [MobileAdminServiceController::class, 'show'])->name('api.v1.admin.services.show');
            Route::patch('/services/{service}', [MobileAdminServiceController::class, 'update'])->name('api.v1.admin.services.update');
            Route::patch('/services/{service}/toggle', [MobileAdminServiceController::class, 'toggle'])->name('api.v1.admin.services.toggle');

            Route::get('/staff', [MobileAdminStaffController::class, 'index'])->name('api.v1.admin.staff.index');
            Route::get('/staff/options', [MobileAdminStaffController::class, 'options'])->name('api.v1.admin.staff.options');
            Route::post('/staff', [MobileAdminStaffController::class, 'store'])->name('api.v1.admin.staff.store');
            Route::get('/staff/{staff}', [MobileAdminStaffController::class, 'show'])->name('api.v1.admin.staff.show');
            Route::patch('/staff/{staff}', [MobileAdminStaffController::class, 'update'])->name('api.v1.admin.staff.update');
            Route::post('/staff/{staff}/weekly-schedules', [MobileAdminScheduleController::class, 'storeWeekly'])->name('api.v1.admin.staff.weekly-schedules.store');
            Route::patch('/staff/{staff}/weekly-schedules/{weeklySchedule}', [MobileAdminScheduleController::class, 'updateWeekly'])->name('api.v1.admin.staff.weekly-schedules.update');
            Route::delete('/staff/{staff}/weekly-schedules/{weeklySchedule}', [MobileAdminScheduleController::class, 'destroyWeekly'])->name('api.v1.admin.staff.weekly-schedules.destroy');
            Route::post('/staff/{staff}/schedule-exceptions', [MobileAdminScheduleController::class, 'storeException'])->name('api.v1.admin.staff.schedule-exceptions.store');
            Route::patch('/staff/{staff}/schedule-exceptions/{scheduleException}', [MobileAdminScheduleController::class, 'updateException'])->name('api.v1.admin.staff.schedule-exceptions.update');
            Route::delete('/staff/{staff}/schedule-exceptions/{scheduleException}', [MobileAdminScheduleController::class, 'destroyException'])->name('api.v1.admin.staff.schedule-exceptions.destroy');

            Route::get('/staff-roster', [AdminWeeklyRosterController::class, 'show'])->name('api.v1.admin.staff-roster.show');
            Route::post('/staff-roster/copy', [AdminWeeklyRosterController::class, 'copy'])->name('api.v1.admin.staff-roster.copy');
            Route::post('/staff-roster/{scheduleWeek}/shifts', [AdminWeeklyRosterController::class, 'storeShift'])->name('api.v1.admin.staff-roster.shifts.store');
            Route::delete('/staff-roster/{scheduleWeek}/shifts/{shift}', [AdminWeeklyRosterController::class, 'destroyShift'])->name('api.v1.admin.staff-roster.shifts.destroy');
            Route::post('/staff-roster/{scheduleWeek}/publish', [AdminWeeklyRosterController::class, 'publish'])->name('api.v1.admin.staff-roster.publish');

            Route::get('/feedback', [MobileAdminFeedbackController::class, 'index'])->name('api.v1.admin.feedback.index');
            Route::get('/feedback/{feedback}', [MobileAdminFeedbackController::class, 'show'])->name('api.v1.admin.feedback.show');
            Route::patch('/feedback/{feedback}/review', [MobileAdminFeedbackController::class, 'review'])->name('api.v1.admin.feedback.review');
            Route::get('/commissions', [MobileAdminCommissionController::class, 'index'])->name('api.v1.admin.commissions.index');
            Route::get('/commissions/{commission}', [MobileAdminCommissionController::class, 'show'])->name('api.v1.admin.commissions.show');
            Route::patch('/commissions/{commission}/pay', [MobileAdminCommissionController::class, 'pay'])->name('api.v1.admin.commissions.pay');
            Route::get('/promotions', [MobileAdminPromotionController::class, 'index'])->name('api.v1.admin.promotions.index');
            Route::get('/promotions/{promotion}', [MobileAdminPromotionController::class, 'show'])->name('api.v1.admin.promotions.show');
            Route::patch('/promotions/settings', [MobileAdminPromotionController::class, 'updateSettings'])->name('api.v1.admin.promotions.settings.update');
            Route::patch('/promotions/{promotion}/dismiss', [MobileAdminPromotionController::class, 'dismiss'])->name('api.v1.admin.promotions.dismiss');
            Route::get('/reports', [MobileAdminReportController::class, 'index'])->name('api.v1.admin.reports.index');
            Route::get('/reports/export', [MobileAdminReportController::class, 'export'])->name('api.v1.admin.reports.export');
            Route::get('/settings', [MobileAdminSettingController::class, 'show'])->name('api.v1.admin.settings.show');
            Route::patch('/settings', [MobileAdminSettingController::class, 'update'])->name('api.v1.admin.settings.update');
        });

        Route::middleware('role:super_admin,admin,receptionist')->prefix('attendance-station')->group(function (): void {
            Route::get('/qr', [MobileAttendanceController::class, 'qr'])->name('api.v1.attendance.qr');
        });

        Route::middleware('super_admin')->prefix('admin')->group(function (): void {
            Route::get('/users', [MobileAdminUserController::class, 'index'])->name('api.v1.admin.users.index');
            Route::post('/users', [MobileAdminUserController::class, 'store'])->name('api.v1.admin.users.store');
            Route::patch('/users/{user}', [MobileAdminUserController::class, 'update'])->name('api.v1.admin.users.update');
        });
    });
});
