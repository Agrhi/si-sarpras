<?php

use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Sarpras\ApprovalFlowController;
use App\Http\Controllers\Sarpras\AssetController;
use App\Http\Controllers\Sarpras\AuditLogController;
use App\Http\Controllers\Sarpras\CategoryController;
use App\Http\Controllers\Sarpras\LoanRequestController;
use App\Http\Controllers\Sarpras\LocationController;
use App\Http\Controllers\Sarpras\MaintenanceReportController;
use App\Http\Controllers\Sarpras\MaintenanceScheduleController;
use App\Http\Controllers\Sarpras\ReportController;
use App\Http\Controllers\Sarpras\RoomController;
use App\Http\Controllers\Teams\TeamInvitationController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::inertia('readme', 'readme')->name('readme');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Notifications
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::post('notifications/{id}/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Sarpras: asset categories
    Route::controller(CategoryController::class)->group(function () {
        Route::get('categories', 'index')->name('categories.index')->middleware('can:category.view');
        Route::get('categories/create', 'create')->name('categories.create')->middleware('can:category.create');
        Route::post('categories', 'store')->name('categories.store')->middleware('can:category.create');
        Route::get('categories/{category}/edit', 'edit')->name('categories.edit')->middleware('can:category.edit');
        Route::put('categories/{category}', 'update')->name('categories.update')->middleware('can:category.edit');
        Route::delete('categories/{category}', 'destroy')->name('categories.destroy')->middleware('can:category.delete');
    });

    // Sarpras: asset locations
    Route::controller(LocationController::class)->group(function () {
        Route::get('locations', 'index')->name('locations.index')->middleware('can:location.view');
        Route::get('locations/create', 'create')->name('locations.create')->middleware('can:location.create');
        Route::post('locations', 'store')->name('locations.store')->middleware('can:location.create');
        Route::get('locations/{location}/edit', 'edit')->name('locations.edit')->middleware('can:location.edit');
        Route::put('locations/{location}', 'update')->name('locations.update')->middleware('can:location.edit');
        Route::delete('locations/{location}', 'destroy')->name('locations.destroy')->middleware('can:location.delete');
    });

    // Sarpras: assets
    Route::controller(AssetController::class)->group(function () {
        Route::get('assets', 'index')->name('assets.index')->middleware('can:asset.view');
        Route::get('assets/scan/{code}', 'scan')->name('assets.scan')->middleware('can:asset.view');
        Route::get('assets/create', 'create')->name('assets.create')->middleware('can:asset.create');
        Route::post('assets', 'store')->name('assets.store')->middleware('can:asset.create');
        Route::get('assets/{asset}', 'show')->name('assets.show')->middleware('can:asset.view');
        Route::get('assets/{asset}/edit', 'edit')->name('assets.edit')->middleware('can:asset.edit');
        Route::put('assets/{asset}', 'update')->name('assets.update')->middleware('can:asset.edit');
        Route::delete('assets/{asset}', 'destroy')->name('assets.destroy')->middleware('can:asset.delete');
        Route::post('assets/{asset}/images', 'storeImage')->name('assets.images.store')->middleware('can:asset.edit');
        Route::delete('assets/{asset}/images/{imageId}', 'destroyImage')->name('assets.images.destroy')->middleware('can:asset.edit');
    });

    // Sarpras: rooms
    Route::controller(RoomController::class)->group(function () {
        Route::get('rooms', 'index')->name('rooms.index')->middleware('can:room.view');
        Route::get('rooms/create', 'create')->name('rooms.create')->middleware('can:room.create');
        Route::post('rooms', 'store')->name('rooms.store')->middleware('can:room.create');
        Route::get('rooms/{room}/edit', 'edit')->name('rooms.edit')->middleware('can:room.edit');
        Route::put('rooms/{room}', 'update')->name('rooms.update')->middleware('can:room.edit');
        Route::delete('rooms/{room}', 'destroy')->name('rooms.destroy')->middleware('can:room.delete');
    });

    // Sarpras: loan requests
    Route::controller(LoanRequestController::class)->group(function () {
        Route::get('loans', 'index')->name('loans.index')->middleware('can:loan.view');
        Route::get('loans/create', 'create')->name('loans.create')->middleware('can:loan.create');
        Route::post('loans', 'store')->name('loans.store')->middleware('can:loan.create');
        Route::get('loans/{loanRequest}', 'show')->name('loans.show')->middleware('can:loan.view');
        Route::post('loans/{loanRequest}/approve', 'approve')->name('loans.approve')->middleware('can:loan.approve');
        Route::post('loans/{loanRequest}/reject', 'reject')->name('loans.reject')->middleware('can:loan.approve');
        Route::post('loans/{loanRequest}/handover', 'handover')->name('loans.handover')->middleware('can:loan.approve');
        Route::post('loans/{loanRequest}/return', 'returnLoan')->name('loans.return')->middleware('can:loan.approve');
        Route::post('loans/{loanRequest}/cancel', 'cancel')->name('loans.cancel');
    });

    // Sarpras: loan approval flow configuration
    Route::controller(ApprovalFlowController::class)->group(function () {
        Route::get('approval-flow', 'edit')->name('approval_flow.edit')->middleware('can:approval-flow.edit');
        Route::put('approval-flow', 'update')->name('approval_flow.update')->middleware('can:approval-flow.edit');
    });

    // Sarpras: damage reports
    Route::controller(MaintenanceReportController::class)->group(function () {
        Route::get('maintenance-reports', 'index')->name('maintenance-reports.index')->middleware('can:maintenance-report.view');
        Route::get('maintenance-reports/create', 'create')->name('maintenance-reports.create')->middleware('can:maintenance-report.create');
        Route::post('maintenance-reports', 'store')->name('maintenance-reports.store')->middleware('can:maintenance-report.create');
        Route::get('maintenance-reports/{maintenanceReport}', 'show')->name('maintenance-reports.show')->middleware('can:maintenance-report.view');
        Route::patch('maintenance-reports/{maintenanceReport}/assign', 'assign')->name('maintenance-reports.assign')->middleware('can:maintenance-report.assign');
        Route::patch('maintenance-reports/{maintenanceReport}/start', 'start')->name('maintenance-reports.start')->middleware('can:maintenance-report.process');
        Route::patch('maintenance-reports/{maintenanceReport}/complete', 'complete')->name('maintenance-reports.complete')->middleware('can:maintenance-report.process');
        Route::patch('maintenance-reports/{maintenanceReport}/cancel', 'cancel')->name('maintenance-reports.cancel')->middleware('can:maintenance-report.cancel');
    });

    // Sarpras: maintenance schedules
    Route::controller(MaintenanceScheduleController::class)->group(function () {
        Route::get('maintenance-schedules', 'index')->name('maintenance-schedules.index')->middleware('can:maintenance-schedule.view');
        Route::get('maintenance-schedules/create', 'create')->name('maintenance-schedules.create')->middleware('can:maintenance-schedule.create');
        Route::post('maintenance-schedules', 'store')->name('maintenance-schedules.store')->middleware('can:maintenance-schedule.create');
        Route::get('maintenance-schedules/{maintenanceSchedule}/edit', 'edit')->name('maintenance-schedules.edit')->middleware('can:maintenance-schedule.edit');
        Route::put('maintenance-schedules/{maintenanceSchedule}', 'update')->name('maintenance-schedules.update')->middleware('can:maintenance-schedule.edit');
        Route::delete('maintenance-schedules/{maintenanceSchedule}', 'destroy')->name('maintenance-schedules.destroy')->middleware('can:maintenance-schedule.delete');
        Route::patch('maintenance-schedules/{maintenanceSchedule}/complete', 'complete')->name('maintenance-schedules.complete')->middleware('can:maintenance-schedule.execute');
    });

    // Sarpras: reports
    Route::controller(ReportController::class)->group(function () {
        Route::get('reports/loans', 'loans')->name('reports.loans')->middleware('can:report.view');
        Route::get('reports/maintenance', 'maintenance')->name('reports.maintenance')->middleware('can:report.view');
    });

    // Sarpras: read-only audit trail
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->name('audit-logs.index')
        ->middleware('can:audit-log.view');
});

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

Route::middleware(['auth', 'verified', 'role:super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('users', UserController::class);
        Route::resource('roles', RoleController::class);
        Route::get('permissions/scan', [PermissionController::class, 'scan'])->name('permissions.scan');
        Route::post('permissions/bulk', [PermissionController::class, 'storeBulk'])->name('permissions.store-bulk');
        Route::resource('permissions', PermissionController::class);
    });

require __DIR__.'/settings.php';
