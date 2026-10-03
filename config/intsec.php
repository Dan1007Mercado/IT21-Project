<?php

use App\Enums\MonitoringSource;

return [
    'max_login_attempts' => env('INTSEC_MAX_LOGIN_ATTEMPTS', 5),
    'login_attempt_window_minutes' => env('INTSEC_LOGIN_ATTEMPT_WINDOW_MINUTES', 5),
    'login_block_duration_minutes' => env('INTSEC_LOGIN_BLOCK_DURATION_MINUTES', 15),
    'failed_login_warning_threshold' => env('INTSEC_FAILED_LOGIN_WARNING_THRESHOLD', 3),
    'repeated_authentication_threshold' => env('INTSEC_REPEATED_AUTHENTICATION_THRESHOLD', 5),
    'repeated_ip_activity_threshold' => env('INTSEC_REPEATED_IP_ACTIVITY_THRESHOLD', 10),
    'brute_force_threshold' => env('INTSEC_BRUTE_FORCE_THRESHOLD', 5),
    'password_spray_threshold' => env('INTSEC_PASSWORD_SPRAY_THRESHOLD', 5),
    'distributed_attack_threshold' => env('INTSEC_DISTRIBUTED_ATTACK_THRESHOLD', 3),
    'repeated_request_threshold' => env('INTSEC_REPEATED_REQUEST_THRESHOLD', 60),
    'request_window_seconds' => env('INTSEC_REQUEST_WINDOW_SECONDS', 60),
    'request_spike_threshold' => env('INTSEC_REQUEST_SPIKE_THRESHOLD', 250),
    'repeated_404_threshold' => env('INTSEC_REPEATED_404_THRESHOLD', 8),
    'repeated_403_threshold' => env('INTSEC_REPEATED_403_THRESHOLD', 6),
    'repeated_401_threshold' => env('INTSEC_REPEATED_401_THRESHOLD', 6),
    'sensitive_path_probe_threshold' => env('INTSEC_SENSITIVE_PATH_PROBE_THRESHOLD', 2),
    'correlation_window_minutes' => env('INTSEC_CORRELATION_WINDOW_MINUTES', 30),
    'alert_cooldown_minutes' => env('INTSEC_ALERT_COOLDOWN_MINUTES', 15),
    'default_ip_block_duration_minutes' => env('INTSEC_DEFAULT_IP_BLOCK_DURATION_MINUTES', 60),
    'api_token' => env('INTSEC_API_TOKEN'),
    'source' => MonitoringSource::Intsec->value,
    'sources' => [
        MonitoringSource::Intsec->value => MonitoringSource::Intsec->label(),
        MonitoringSource::HotelBooking->value => MonitoringSource::HotelBooking->label(),
    ],
    'event_sources' => array_values(array_unique(array_filter(array_map('trim', explode(',', (string) env('INTSEC_EVENT_SOURCES', MonitoringSource::HotelBooking->value)))))),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('INTSEC_TRUSTED_PROXIES', ''))))),
    'request_monitoring' => [
        'exclusions' => [
            '/up', '/favicon.ico', '/build/*', '/css/*', '/js/*', '/images/*', '/storage/*',
            '/api/security/*', '/broadcasting/auth',
        ],
    ],
    'ip_intelligence' => [
        'enabled' => filter_var(env('INTSEC_IP_ENRICHMENT_ENABLED', false), FILTER_VALIDATE_BOOL),
        'cache_hours' => env('INTSEC_IP_ENRICHMENT_CACHE_HOURS', 168),
    ],
    'suspicious_paths' => [
        '/.env', '/.git/config', '/wp-admin', '/phpmyadmin', '/vendor/phpunit',
    ],
    'decoy_paths' => ['/security/monitored-login', '/decoy/login'],
    'protected_paths' => ['/admin*', '/alerts*', '/incidents*', '/ip-management*'],
];
