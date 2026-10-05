<?php

use App\Http\Controllers\AdminSecurityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\MfaEnrollmentController;
use App\Http\Controllers\Auth\RecoveryCodesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IpManagementController;
use App\Http\Controllers\MfaManagementController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SecurityAlertController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureIpNotBlocked;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/debug-ip', function (Request $request) {
    return [
        'laravel_ip' => $request->ip(),
        'remote_addr' => $request->server('REMOTE_ADDR'),

        'cf_connecting_ip' => $request->header('CF-Connecting-IP'),
        'x_forwarded_for' => $request->header('X-Forwarded-For'),
        'x_real_ip' => $request->header('X-Real-IP'),
    ];
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware(['throttle:5,1', EnsureIpNotBlocked::class])
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::prefix('mfa')->name('mfa.')->group(function (): void {
        Route::get('/enroll', [MfaEnrollmentController::class, 'show'])->name('enrollment.show');
        Route::post('/enroll/email/send', [MfaEnrollmentController::class, 'sendEmail'])->name('enrollment.email.send');
        Route::post('/enroll/email/verify', [MfaEnrollmentController::class, 'verifyEmail'])->name('enrollment.email.verify');
        Route::get('/enroll/authenticator', [MfaEnrollmentController::class, 'authenticator'])->name('enrollment.authenticator');
        Route::post('/enroll/authenticator', [MfaEnrollmentController::class, 'confirmAuthenticator'])->name('enrollment.authenticator.confirm');

        Route::get('/challenge', [MfaChallengeController::class, 'show'])->name('challenge.show');
        Route::post('/challenge/authenticator', [MfaChallengeController::class, 'verifyTotp'])->name('challenge.totp.verify');
        Route::get('/challenge/email', [MfaChallengeController::class, 'email'])->name('challenge.email');
        Route::post('/challenge/email/send', [MfaChallengeController::class, 'sendEmail'])->name('challenge.email.send');
        Route::post('/challenge/email/verify', [MfaChallengeController::class, 'verifyEmail'])->name('challenge.email.verify');
        Route::get('/challenge/recovery', [MfaChallengeController::class, 'recovery'])->name('challenge.recovery');
        Route::post('/challenge/recovery/code', [MfaChallengeController::class, 'verifyRecoveryCode'])->name('challenge.recovery.code');
        Route::post('/challenge/recovery/file', [MfaChallengeController::class, 'verifyRecoveryFile'])->name('challenge.recovery.file');

        Route::get('/recovery-codes', [RecoveryCodesController::class, 'show'])->name('recovery-codes.show');
        Route::get('/recovery-codes/download', [RecoveryCodesController::class, 'download'])->name('recovery-codes.download');
        Route::post('/recovery-codes/finish', [RecoveryCodesController::class, 'finish'])->name('recovery-codes.finish');
    });

    Route::middleware(['mfa.configured', 'mfa.verified'])->group(function (): void {

        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/ip-locations', [DashboardController::class, 'ipLocations'])->name('ip-locations');
        Route::get('/ddos-monitoring', [DashboardController::class, 'ddosMonitoring'])->name('ddos-monitoring');
        Route::get('/attack-frequency', [DashboardController::class, 'attackFrequency'])->name('attack-frequency');
        Route::get('/login-activity', [DashboardController::class, 'loginActivity'])->name('login-activity');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
        Route::post('/profile/mfa/recovery-codes', [MfaManagementController::class, 'regenerateRecoveryCodes'])->name('profile.mfa.recovery-codes');
        Route::post('/profile/mfa/authenticator/replace', [MfaManagementController::class, 'startReplacement'])->name('profile.mfa.replace.start');
        Route::get('/profile/mfa/authenticator/replace', [MfaManagementController::class, 'showReplacement'])->name('profile.mfa.replace.show');
        Route::post('/profile/mfa/authenticator/replace/confirm', [MfaManagementController::class, 'confirmReplacement'])->name('profile.mfa.replace.confirm');

        Route::middleware('admin')->group(function (): void {
            Route::prefix('monitoring/{source}')->whereIn('source', ['intsec', 'hotel-booking'])->name('monitoring.')->group(function (): void {
                Route::get('/', [MonitoringController::class, 'overview'])->name('overview');
                Route::get('/login-activity', [DashboardController::class, 'loginActivity'])->name('login-activity');
                Route::get('/ip-monitoring', [DashboardController::class, 'ipLocations'])->name('ip-locations');
                Route::get('/request-activity', [MonitoringController::class, 'requestActivities'])->name('request-activities');
                Route::get('/attack-frequency', [DashboardController::class, 'attackFrequency'])->name('attack-frequency');
                Route::get('/ddos-monitoring', [DashboardController::class, 'ddosMonitoring'])->name('ddos-monitoring');
                Route::get('/security-events', [MonitoringController::class, 'securityEvents'])->name('security-events');
            });

            Route::get('/admin', [UserController::class, 'index'])->name('admin.index');
            Route::resource('/admin/users', UserController::class)->names('users');

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

            // Security alerts
            Route::get('/alerts', [SecurityAlertController::class, 'index'])->name('alerts.index');
            Route::post('/alerts', [SecurityAlertController::class, 'store'])->name('alerts.store');
            Route::post('/alerts/bulk', [SecurityAlertController::class, 'bulkUpdate'])->name('alerts.bulk');
            Route::get('/alerts/export', [SecurityAlertController::class, 'export'])->name('alerts.export');
            Route::get('/alerts/{alert}', [SecurityAlertController::class, 'show'])->name('alerts.show');
            Route::post('/alerts/{alert}/acknowledge', [SecurityAlertController::class, 'acknowledge'])->name('alerts.acknowledge');
            Route::patch('/alerts/{alert}/status', [SecurityAlertController::class, 'updateStatus'])->name('alerts.status.update');
            Route::patch('/alerts/{alert}/severity', [SecurityAlertController::class, 'updateSeverity'])->name('alerts.severity.update');
            Route::post('/alerts/{alert}/assign', [SecurityAlertController::class, 'assign'])->name('alerts.assign');
            Route::post('/alerts/{alert}/remarks', [SecurityAlertController::class, 'storeRemark'])->name('alerts.remarks.store');
            Route::post('/alerts/{alert}/false-positive', [SecurityAlertController::class, 'markFalsePositive'])->name('alerts.false-positive');
            Route::post('/alerts/{alert}/attach-incident', [SecurityAlertController::class, 'attachIncident'])->name('alerts.attach-incident');
            Route::post('/alerts/{alert}/create-incident', [SecurityAlertController::class, 'createIncident'])->name('alerts.create-incident');
            Route::post('/alerts/{alert}/block-ip', [SecurityAlertController::class, 'blockIp'])->name('alerts.block-ip');

            Route::post('/incidents/{incident}/block-ip', [IncidentController::class, 'blockIp'])->name('incidents.block-ip');

            // IP Management (centralized ALLOW / BLOCK enforcement layer)
            Route::get('/ip-management', [IpManagementController::class, 'index'])->name('ip-management.index');
            Route::post('/ip-management', [IpManagementController::class, 'store'])->name('ip-management.store');
            Route::put('/ip-management/{rule}', [IpManagementController::class, 'update'])->name('ip-management.update');
            Route::patch('/ip-management/{rule}/toggle', [IpManagementController::class, 'toggle'])->name('ip-management.toggle');
            Route::patch('/ip-management/{rule}/action', [IpManagementController::class, 'switchAction'])->name('ip-management.action');
            Route::delete('/ip-management/{rule}', [IpManagementController::class, 'destroy'])->name('ip-management.destroy');
        });
    });
});
