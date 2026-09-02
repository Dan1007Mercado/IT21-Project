<?php

use App\Http\Controllers\AdminSecurityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\EnsureIpNotBlocked;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware(['throttle:5,1', EnsureIpNotBlocked::class])
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/ip-locations', [DashboardController::class, 'ipLocations'])->name('ip-locations');
    Route::get('/ddos-monitoring', [DashboardController::class, 'ddosMonitoring'])->name('ddos-monitoring');
    Route::get('/attack-frequency', [DashboardController::class, 'attackFrequency'])->name('attack-frequency');
    Route::get('/login-activity', [DashboardController::class, 'loginActivity'])->name('login-activity');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('admin')->group(function (): void {
        Route::get('/admin', function () {
            return view('admin.index');
        })->name('admin.index');

        Route::get('/admin/settings', [AdminSecurityController::class, 'settings'])->name('admin.settings');
        Route::post('/admin/settings', [AdminSecurityController::class, 'saveSettings'])->name('admin.settings.store');

        Route::get('/admin/audit-logs', [AdminSecurityController::class, 'auditLogs'])->name('admin.audit-logs');

        Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
        Route::post('/incidents', [IncidentController::class, 'store'])->name('incidents.store');
        Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
        Route::put('/incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
        Route::patch('/incidents/{incident}/severity', [IncidentController::class, 'updateSeverity'])->name('incidents.severity.update');
        Route::post('/incidents/{incident}/remarks', [IncidentController::class, 'storeRemark'])->name('incidents.remarks.store');
        Route::post('/incidents/{incident}/response', [IncidentController::class, 'storeResponseAction'])->name('incidents.response.store');
        Route::patch('/incidents/{incident}/status', [IncidentController::class, 'updateStatus'])->name('incidents.status.update');
        Route::post('/incidents/{incident}/assign', [IncidentController::class, 'assign'])->name('incidents.assign');
    });
});
