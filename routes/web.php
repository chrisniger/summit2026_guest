<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\FollowUpReportController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\GuestImportController;
use App\Http\Controllers\InfoAttendanceController;
use App\Http\Controllers\InfoAttendeeController;
use App\Http\Controllers\InfoImportController;
use App\Http\Controllers\InfoReportController;
use App\Http\Controllers\PublicAttendanceController;
use App\Http\Controllers\StaffController;
use App\Models\AuditLog;
use App\Services\Operations;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/info-com/attendance', [PublicAttendanceController::class, 'show'])->middleware('throttle:60,1')->name('attendance.public');
Route::post('/info-com/attendance', [PublicAttendanceController::class, 'store'])->middleware('throttle:20,1')->name('attendance.public.store');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'emailReset'])->middleware('throttle:passwords')->name('password.email');
    Route::get('/reset-password/{token}', fn (string $token) => view('auth.reset-password', ['token' => $token]))->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:passwords')->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', fn () => redirect()->route(auth()->user()->workspace()))->name('dashboard');
    Route::get('/admin', [DashboardController::class, 'show'])->middleware('can:platform.manage')->name('admin');
    Route::get('/info-com', [DashboardController::class, 'show'])->middleware('can:workspace.info_com')->name('info');
    Route::middleware('can:info.access')->prefix('info-com')->name('info.')->group(function () {
        Route::view('/check-in', 'info.check-in')->name('check-in');
        Route::get('/attendees/create', [InfoAttendeeController::class, 'create'])->name('attendees.create');
        Route::post('/attendees', [InfoAttendeeController::class, 'store'])->middleware('can:info.attendee.create')->name('attendees.store');
        Route::get('/attendees/{attendee}', [InfoAttendeeController::class, 'show'])->name('attendees.show');
        Route::get('/attendees/{attendee}/edit', [InfoAttendeeController::class, 'edit'])->name('attendees.edit');
        Route::patch('/attendees/{attendee}', [InfoAttendeeController::class, 'update'])->name('attendees.update');
        Route::post('/attendees/{attendee}/check-in', [InfoAttendanceController::class, 'store'])->middleware('can:info.attendance.mark')->name('attendance.store');
        Route::post('/attendance/{record}/correct', [InfoAttendanceController::class, 'correct'])->middleware('can:platform.manage')->name('attendance.correct');
        Route::get('/reports', [InfoReportController::class, 'index'])->middleware('can:info.report.view')->name('reports');
        Route::get('/reports/export', [InfoReportController::class, 'export'])->middleware('can:platform.manage')->name('reports.export');
        Route::middleware('can:info.import')->group(function () {
            Route::get('/imports', [InfoImportController::class, 'index'])->name('imports');
            Route::get('/imports/template', [InfoImportController::class, 'template'])->name('imports.template');
            Route::post('/imports', [InfoImportController::class, 'store'])->name('imports.store');
            Route::get('/imports/{batch}', [InfoImportController::class, 'show'])->name('imports.show');
            Route::post('/imports/{batch}/rows/{row}', [InfoImportController::class, 'resolve'])->name('imports.resolve');
            Route::post('/imports/{batch}/commit', [InfoImportController::class, 'commit'])->name('imports.commit');
            Route::get('/imports/{batch}/results', [InfoImportController::class, 'results'])->name('imports.results');
        });
    });
    Route::get('/follow-up', [DashboardController::class, 'show'])->middleware('can:workspace.follow_up_admin')->name('follow-up');
    Route::get('/guest-service', [DashboardController::class, 'show'])->middleware('can:workspace.data_entry')->name('guest-entry');
    Route::get('/my-guests', [DashboardController::class, 'show'])->middleware('can:workspace.officer')->name('my-guests');
    Route::middleware('can:guest.access')->prefix('follow-up')->group(function () {
        Route::post('/assignments/preview', [FollowUpController::class, 'preview'])->middleware('can:guest.assign')->name('follow-up.assignments.preview');
        Route::post('/assignments/commit', [FollowUpController::class, 'commit'])->middleware('can:guest.assign')->name('follow-up.assignments.commit');
        Route::get('/reports', [FollowUpReportController::class, 'index'])->middleware('can:follow-up.reports')->name('follow-up.reports');
        Route::get('/reports/export', [FollowUpReportController::class, 'export'])->middleware('can:follow-up.reports')->name('follow-up.reports.export');
        Route::get('/guests', [GuestController::class, 'index'])->name('guests.index');
        Route::get('/guests/create', [GuestController::class, 'create'])->name('guests.create');
        Route::post('/guests', [GuestController::class, 'store'])->middleware('throttle:120,1')->name('guests.store');
        Route::get('/guests/{guest}', [GuestController::class, 'show'])->name('guests.show');
        Route::post('/guests/{guest}/activities', [FollowUpController::class, 'activity'])->middleware('throttle:60,1')->name('follow-up.activities.store');
        Route::get('/guests/{guest}/edit', [GuestController::class, 'edit'])->name('guests.edit');
        Route::patch('/guests/{guest}', [GuestController::class, 'update'])->middleware('throttle:120,1')->name('guests.update');
        Route::middleware('can:guest.import')->prefix('imports')->name('guest-imports.')->group(function () {
            Route::get('/', [GuestImportController::class, 'index'])->name('index');
            Route::post('/', [GuestImportController::class, 'store'])->middleware('throttle:10,1')->name('store');
            Route::get('/template', [GuestImportController::class, 'template'])->name('template');
            Route::post('/historical-session', [GuestImportController::class, 'historicalSession'])->middleware('can:platform.manage')->name('historical-session');
            Route::get('/{batch}', [GuestImportController::class, 'show'])->name('show');
            Route::post('/{batch}/preview', [GuestImportController::class, 'preview'])->name('preview');
            Route::post('/{batch}/rows/{row}', [GuestImportController::class, 'resolve'])->name('resolve');
            Route::post('/{batch}/commit', [GuestImportController::class, 'commit'])->name('commit');
            Route::get('/{batch}/results', [GuestImportController::class, 'results'])->name('results');
        });
    });
    Route::get('/access-denied', fn () => response()->view('errors.403', [], 403))->name('access-denied');
    Route::middleware('can:platform.manage')->prefix('admin')->name('admin.')->group(function () {
        Route::view('/sessions', 'admin.sessions')->name('sessions');
        Route::get('/staff', [StaffController::class, 'index'])->name('staff');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::patch('/staff/{user}', [StaffController::class, 'update'])->name('staff.update');
        Route::get('/audit', fn () => view('admin.audit', ['logs' => AuditLog::orderByDesc('id')->paginate(30)]))->name('audit');
        Route::get('/operations', fn () => view('admin.operations', ['checks' => app(Operations::class)->checks()]))->name('operations');
    });
});
