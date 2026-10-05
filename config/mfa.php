<?php

return [
    'issuer' => env('MFA_ISSUER', 'INTSEC'),
    'totp_period' => 30,
    'totp_digits' => 6,
    'totp_window' => 1,
    'max_attempts' => (int) env('MFA_MAX_ATTEMPTS', 5),
    'email_otp_ttl' => (int) env('MFA_EMAIL_OTP_TTL', 300),
    'email_otp_resend_cooldown' => (int) env('MFA_EMAIL_OTP_RESEND_COOLDOWN', 60),
    'recovery_code_count' => (int) env('MFA_RECOVERY_CODE_COUNT', 10),
    'recent_verification_seconds' => 600,
    'recovery_file_max_kilobytes' => 64,
];
