<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\StaffScheduleExceptionController as AdminStaffScheduleExceptionController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Admin\StaffWeeklyScheduleController as AdminStaffWeeklyScheduleController;
use App\Http\Controllers\Customer\AppointmentIndexController as CustomerAppointmentIndexController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function (Request $request) {
    return redirect()->route($request->user()->homeRouteName());
})->middleware(['auth', 'active', 'verified'])->name('dashboard');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'active', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::view('/appointments', 'modules.placeholder', [
            'eyebrow' => 'Admin module',
            'title' => 'Appointments',
            'description' => 'Review booking requests, assign staff, and manage daily appointment status.',
            'status' => 'Phase 6',
            'actionLabel' => 'Back to dashboard',
            'actionRoute' => 'admin.dashboard',
            'cards' => [
                ['label' => 'Queue', 'value' => 'Pending requests'],
                ['label' => 'Calendar', 'value' => 'Scheduled visits'],
                ['label' => 'Actions', 'value' => 'Confirm, complete, cancel'],
            ],
        ])->name('appointments.index');
        Route::view('/customers', 'modules.placeholder', [
            'eyebrow' => 'Admin module',
            'title' => 'Customers',
            'description' => 'View customer profiles, appointment history, transactions, feedback, and promotion context.',
            'status' => 'Phase 5',
            'actionLabel' => 'Back to dashboard',
            'actionRoute' => 'admin.dashboard',
            'cards' => [
                ['label' => 'Profiles', 'value' => 'Contact records'],
                ['label' => 'History', 'value' => 'Visits and payments'],
                ['label' => 'Care notes', 'value' => 'Operational context'],
            ],
        ])->name('customers.index');
        Route::get('/staff/{staff}/weekly-schedules/create', [AdminStaffWeeklyScheduleController::class, 'create'])->name('staff.weekly-schedules.create');
        Route::post('/staff/{staff}/weekly-schedules', [AdminStaffWeeklyScheduleController::class, 'store'])->name('staff.weekly-schedules.store');
        Route::get('/staff/{staff}/weekly-schedules/{weeklySchedule}/edit', [AdminStaffWeeklyScheduleController::class, 'edit'])->name('staff.weekly-schedules.edit');
        Route::patch('/staff/{staff}/weekly-schedules/{weeklySchedule}', [AdminStaffWeeklyScheduleController::class, 'update'])->name('staff.weekly-schedules.update');
        Route::delete('/staff/{staff}/weekly-schedules/{weeklySchedule}', [AdminStaffWeeklyScheduleController::class, 'destroy'])->name('staff.weekly-schedules.destroy');
        Route::get('/staff/{staff}/schedule-exceptions/create', [AdminStaffScheduleExceptionController::class, 'create'])->name('staff.schedule-exceptions.create');
        Route::post('/staff/{staff}/schedule-exceptions', [AdminStaffScheduleExceptionController::class, 'store'])->name('staff.schedule-exceptions.store');
        Route::get('/staff/{staff}/schedule-exceptions/{scheduleException}/edit', [AdminStaffScheduleExceptionController::class, 'edit'])->name('staff.schedule-exceptions.edit');
        Route::patch('/staff/{staff}/schedule-exceptions/{scheduleException}', [AdminStaffScheduleExceptionController::class, 'update'])->name('staff.schedule-exceptions.update');
        Route::delete('/staff/{staff}/schedule-exceptions/{scheduleException}', [AdminStaffScheduleExceptionController::class, 'destroy'])->name('staff.schedule-exceptions.destroy');
        Route::resource('staff', AdminStaffController::class)->except('destroy');
        Route::patch('/services/{service}/toggle', [AdminServiceController::class, 'toggle'])->name('services.toggle');
        Route::resource('services', AdminServiceController::class)->except('destroy');
        Route::view('/transactions', 'modules.placeholder', [
            'eyebrow' => 'Admin module',
            'title' => 'Transactions',
            'description' => 'Record manual payments and review customer service transaction history.',
            'status' => 'Phase 7',
            'actionLabel' => 'Back to dashboard',
            'actionRoute' => 'admin.dashboard',
            'cards' => [
                ['label' => 'Payments', 'value' => 'Cash, GCash, transfer'],
                ['label' => 'Status', 'value' => 'Paid and unpaid'],
                ['label' => 'Records', 'value' => 'Customer history'],
            ],
        ])->name('transactions.index');
        Route::view('/promotions', 'modules.placeholder', [
            'eyebrow' => 'Admin module',
            'title' => 'Promotions',
            'description' => 'Review RFM segments, rule-based suggestions, and promotion follow-up status.',
            'status' => 'Phase 9',
            'actionLabel' => 'Back to dashboard',
            'actionRoute' => 'admin.dashboard',
            'cards' => [
                ['label' => 'Segments', 'value' => 'RFM groups'],
                ['label' => 'Rules', 'value' => 'Suggested offers'],
                ['label' => 'Review', 'value' => 'Apply or dismiss'],
            ],
        ])->name('promotions.index');
        Route::view('/feedback', 'modules.placeholder', [
            'eyebrow' => 'Admin module',
            'title' => 'Feedback',
            'description' => 'Review customer ratings, comments, and simple sentiment summaries.',
            'status' => 'Phase 8',
            'actionLabel' => 'Back to dashboard',
            'actionRoute' => 'admin.dashboard',
            'cards' => [
                ['label' => 'Ratings', 'value' => 'Service reviews'],
                ['label' => 'Sentiment', 'value' => 'Positive, neutral, negative'],
                ['label' => 'Follow-up', 'value' => 'Customer care'],
            ],
        ])->name('feedback.index');
        Route::view('/reports', 'modules.placeholder', [
            'eyebrow' => 'Admin module',
            'title' => 'Reports',
            'description' => 'Filter and export operational summaries for appointments, revenue, customers, and feedback.',
            'status' => 'Phase 10',
            'actionLabel' => 'Back to dashboard',
            'actionRoute' => 'admin.dashboard',
            'cards' => [
                ['label' => 'Filters', 'value' => 'Date and status'],
                ['label' => 'Exports', 'value' => 'CSV downloads'],
                ['label' => 'Summaries', 'value' => 'Management view'],
            ],
        ])->name('reports.index');
        Route::view('/settings', 'modules.placeholder', [
            'eyebrow' => 'Admin module',
            'title' => 'Settings',
            'description' => 'Maintain business details, user status, and practical system defaults.',
            'status' => 'Phase 11',
            'actionLabel' => 'Back to dashboard',
            'actionRoute' => 'admin.dashboard',
            'cards' => [
                ['label' => 'Business', 'value' => 'Contact details'],
                ['label' => 'Users', 'value' => 'Account status'],
                ['label' => 'Defaults', 'value' => 'Operating values'],
            ],
        ])->name('settings.index');
    });

Route::middleware(['auth', 'active', 'verified', 'role:staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('/dashboard', StaffDashboardController::class)->name('dashboard');
        Route::view('/appointments', 'modules.placeholder', [
            'eyebrow' => 'Staff module',
            'title' => 'Appointments',
            'description' => 'Handle assigned visits, pending requests, and daily appointment actions.',
            'status' => 'Phase 6',
            'actionLabel' => 'Back to dashboard',
            'actionRoute' => 'staff.dashboard',
            'cards' => [
                ['label' => 'Today', 'value' => 'Assigned visits'],
                ['label' => 'Pending', 'value' => 'Requests to review'],
                ['label' => 'Service', 'value' => 'Complete or no-show'],
            ],
        ])->name('appointments.index');
        Route::view('/customers', 'modules.placeholder', [
            'eyebrow' => 'Staff module',
            'title' => 'Customers',
            'description' => 'Look up operational customer details needed for appointment service.',
            'status' => 'Phase 5',
            'actionLabel' => 'Back to dashboard',
            'actionRoute' => 'staff.dashboard',
            'cards' => [
                ['label' => 'Lookup', 'value' => 'Customer contact'],
                ['label' => 'History', 'value' => 'Visits and notes'],
                ['label' => 'Care', 'value' => 'Relevant feedback'],
            ],
        ])->name('customers.index');
        Route::view('/transactions', 'modules.placeholder', [
            'eyebrow' => 'Staff module',
            'title' => 'Transactions',
            'description' => 'Record manual payments from confirmed or completed appointments.',
            'status' => 'Phase 7',
            'actionLabel' => 'Back to dashboard',
            'actionRoute' => 'staff.dashboard',
            'cards' => [
                ['label' => 'Entry', 'value' => 'Manual payment'],
                ['label' => 'Method', 'value' => 'Cash, GCash, transfer'],
                ['label' => 'Status', 'value' => 'Paid or partial'],
            ],
        ])->name('transactions.index');
        Route::view('/feedback', 'modules.placeholder', [
            'eyebrow' => 'Staff module',
            'title' => 'Feedback',
            'description' => 'View service feedback connected to daily operations.',
            'status' => 'Phase 8',
            'actionLabel' => 'Back to dashboard',
            'actionRoute' => 'staff.dashboard',
            'cards' => [
                ['label' => 'Reviews', 'value' => 'Service comments'],
                ['label' => 'Ratings', 'value' => 'Customer sentiment'],
                ['label' => 'Context', 'value' => 'Related appointment'],
            ],
        ])->name('feedback.index');
    });

Route::middleware(['auth', 'active', 'verified', 'role:customer'])
    ->prefix('customer')
    ->name('customer.')
    ->group(function () {
        Route::get('/appointments', CustomerAppointmentIndexController::class)->name('appointments.index');
        Route::view('/appointments/create', 'modules.placeholder', [
            'eyebrow' => 'Customer lounge',
            'title' => 'Request appointment',
            'description' => 'Choose a service, preferred schedule, and notes for staff review.',
            'status' => 'Phase 6',
            'actionLabel' => 'Back to appointments',
            'actionRoute' => 'customer.appointments.index',
            'cards' => [
                ['label' => 'Service', 'value' => 'Treatment selection'],
                ['label' => 'Schedule', 'value' => 'Preferred time'],
                ['label' => 'Review', 'value' => 'Staff confirmation'],
            ],
        ])->name('appointments.create');
        Route::view('/feedback', 'modules.placeholder', [
            'eyebrow' => 'Customer lounge',
            'title' => 'Feedback',
            'description' => 'Share ratings and comments after completed appointments.',
            'status' => 'Phase 8',
            'actionLabel' => 'Back to appointments',
            'actionRoute' => 'customer.appointments.index',
            'cards' => [
                ['label' => 'Completed', 'value' => 'Eligible visits'],
                ['label' => 'Rating', 'value' => 'Service stars'],
                ['label' => 'Comment', 'value' => 'Care notes'],
            ],
        ])->name('feedback.index');
        Route::redirect('/profile', '/profile')->name('profile.edit');
    });

require __DIR__.'/auth.php';
