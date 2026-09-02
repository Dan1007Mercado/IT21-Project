<?php

namespace App\Services\Security;

use App\Models\SystemSetting;

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
        if (! \Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
            return;
        }

        config(['intsec' => array_merge(config('intsec', []), self::all())]);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
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
            'default_ip_block_duration_minutes',
        ];

        if (in_array($key, $numericKeys, true) && is_numeric($value)) {
            return (int) $value;
        }

        return $value;
    }
}
