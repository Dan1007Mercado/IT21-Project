<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Services\Security\IntsecSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSecurityController extends Controller
{
    public function settings(Request $request): View
    {
        $data = IntsecSettings::all();

        return view('admin.settings', [
            'settings' => $data,
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'max_login_attempts' => ['required', 'integer', 'min:1'],
            'login_attempt_window_minutes' => ['required', 'integer', 'min:1'],
            'login_block_duration_minutes' => ['required', 'integer', 'min:1'],
            'failed_login_warning_threshold' => ['required', 'integer', 'min:1'],
            'repeated_authentication_threshold' => ['required', 'integer', 'min:1'],
            'repeated_ip_activity_threshold' => ['required', 'integer', 'min:1'],
            'default_ip_block_duration_minutes' => ['required', 'integer', 'min:1'],
        ]);

        $changes = [];
        foreach ($validated as $key => $value) {
            $previous = IntsecSettings::get($key, null);
            if ($previous == $value) {
                continue;
            }

            IntsecSettings::set($key, $value);
            $changes[$key] = ['previous' => $previous, 'new' => $value];
        }

        IntsecSettings::refreshConfig();

        if (! empty($changes)) {
            AuditLog::record(
                'system_settings_changed',
                'system_setting',
                'System Settings',
                null,
                collect($changes)->map(fn ($change) => $change['previous'])->all(),
                collect($changes)->map(fn ($change) => $change['new'])->all(),
                'System security settings were updated by an administrator.',
                $request->ip(),
                $request->user(),
            );
        }

        return redirect()->route('admin.settings')->with('status', 'settings-updated');
    }

    public function auditLogs(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('actor')
            ->latest('occurred_at')
            ->paginate(10);

        return view('admin.audit-logs', [
            'logs' => $logs,
        ]);
    }
}
