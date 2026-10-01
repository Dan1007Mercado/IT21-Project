<?php

namespace App\Services\Security;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;

class IntsecSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return [
            'max_login_attempts' => self::getInt('max_login_attempts', 5),
            'login_attempt_window_minutes' => self::getInt('login_attempt_window_minutes', 5),
            'login_block_duration_minutes' => self::getInt('login_block_duration_minutes', 15),
            'failed_login_warning_threshold' => self::getInt('failed_login_warning_threshold', 3),
            'repeated_authentication_threshold' => self::getInt('repeated_authentication_threshold', 5),
            'repeated_ip_activity_threshold' => self::getInt('repeated_ip_activity_threshold', 10),
            'brute_force_threshold' => self::getInt('brute_force_threshold', 5),
            'password_spray_threshold' => self::getInt('password_spray_threshold', 5),
            'distributed_attack_threshold' => self::getInt('distributed_attack_threshold', 3),
            'repeated_request_threshold' => self::getInt('repeated_request_threshold', 60),
            'request_window_seconds' => self::getInt('request_window_seconds', 60),
            'request_spike_threshold' => self::getInt('request_spike_threshold', 250),
            'repeated_404_threshold' => self::getInt('repeated_404_threshold', 8),
            'repeated_403_threshold' => self::getInt('repeated_403_threshold', 6),
            'repeated_401_threshold' => self::getInt('repeated_401_threshold', 6),
            'sensitive_path_probe_threshold' => self::getInt('sensitive_path_probe_threshold', 2),
            'correlation_window_minutes' => self::getInt('correlation_window_minutes', 30),
            'alert_cooldown_minutes' => self::getInt('alert_cooldown_minutes', 15),
            'ip_enrichment_cache_hours' => self::getInt('ip_enrichment_cache_hours', 168),
            'ip_enrichment_enabled' => self::getBool('ip_enrichment_enabled', (bool) config('intsec.ip_intelligence.enabled', false)),
            'default_ip_block_duration_minutes' => self::getInt('default_ip_block_duration_minutes', 60),
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = SystemSetting::getValue($key, $default);

        return self::castValue($key, $value);
    }

    public static function set(string $key, mixed $value): SystemSetting
    {
        return SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value],
        );
    }

    public static function refreshConfig(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        config(['intsec' => array_merge(config('intsec', []), self::all())]);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOL);
    }

    protected static function castValue(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $numericKeys = [
            'max_login_attempts',
            'login_attempt_window_minutes',
            'login_block_duration_minutes',
            'failed_login_warning_threshold',
            'repeated_authentication_threshold',
            'repeated_ip_activity_threshold',
            'brute_force_threshold',
            'password_spray_threshold',
            'distributed_attack_threshold',
            'repeated_request_threshold',
            'request_window_seconds',
            'request_spike_threshold',
            'repeated_404_threshold',
            'repeated_403_threshold',
            'repeated_401_threshold',
            'sensitive_path_probe_threshold',
            'correlation_window_minutes',
            'alert_cooldown_minutes',
            'ip_enrichment_cache_hours',
            'default_ip_block_duration_minutes',
        ];

        if (in_array($key, $numericKeys, true) && is_numeric($value)) {
            return (int) $value;
        }

        if ($key === 'ip_enrichment_enabled') {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return $value;
    }
}
