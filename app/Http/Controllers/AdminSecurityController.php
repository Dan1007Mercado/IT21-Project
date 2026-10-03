<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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
            'brute_force_threshold' => ['sometimes', 'integer', 'min:2'],
            'password_spray_threshold' => ['sometimes', 'integer', 'min:2'],
            'distributed_attack_threshold' => ['sometimes', 'integer', 'min:2'],
            'repeated_request_threshold' => ['sometimes', 'integer', 'min:2'],
            'request_window_seconds' => ['sometimes', 'integer', 'min:10'],
            'request_spike_threshold' => ['sometimes', 'integer', 'min:2'],
            'repeated_404_threshold' => ['sometimes', 'integer', 'min:2'],
            'repeated_403_threshold' => ['sometimes', 'integer', 'min:2'],
            'repeated_401_threshold' => ['sometimes', 'integer', 'min:2'],
            'sensitive_path_probe_threshold' => ['sometimes', 'integer', 'min:1'],
            'correlation_window_minutes' => ['sometimes', 'integer', 'min:1'],
            'alert_cooldown_minutes' => ['sometimes', 'integer', 'min:1'],
            'ip_enrichment_cache_hours' => ['sometimes', 'integer', 'min:1'],
            'ip_enrichment_enabled' => ['nullable', 'boolean'],
            'default_ip_block_duration_minutes' => ['required', 'integer', 'min:1'],
        ]);

        if ($request->has('ip_enrichment_enabled')) {
            $validated['ip_enrichment_enabled'] = $request->boolean('ip_enrichment_enabled');
        }

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
        $query = AuditLog::query()->with('actor');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($nested) use ($search): void {
                $nested->where('action', 'like', '%'.$search.'%')
                    ->orWhere('resource_type', 'like', '%'.$search.'%')
                    ->orWhere('resource_name', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('actor', fn ($actor) => $actor
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%'));
            });
        }

        $logs = $query
            ->latest('occurred_at')
            ->simplePaginate(10)
            ->withQueryString();

        return view('admin.audit-logs', [
            'logs' => $logs,
        ]);
    }
}
