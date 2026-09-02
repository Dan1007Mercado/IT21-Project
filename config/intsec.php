<?php

return [
    'max_login_attempts' => env('INTSEC_MAX_LOGIN_ATTEMPTS', 5),
    'login_attempt_window_minutes' => env('INTSEC_LOGIN_ATTEMPT_WINDOW_MINUTES', 5),
    'login_block_duration_minutes' => env('INTSEC_LOGIN_BLOCK_DURATION_MINUTES', 15),
    'failed_login_warning_threshold' => env('INTSEC_FAILED_LOGIN_WARNING_THRESHOLD', 3),
    'repeated_authentication_threshold' => env('INTSEC_REPEATED_AUTHENTICATION_THRESHOLD', 5),
    'repeated_ip_activity_threshold' => env('INTSEC_REPEATED_IP_ACTIVITY_THRESHOLD', 10),
    'default_ip_block_duration_minutes' => env('INTSEC_DEFAULT_IP_BLOCK_DURATION_MINUTES', 60),
];
