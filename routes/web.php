<?php

use App\Http\Controllers\AdminSecurityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
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
    });
});
