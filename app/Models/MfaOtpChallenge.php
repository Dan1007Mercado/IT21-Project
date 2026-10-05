<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfaOtpChallenge extends Model
{
    public const PURPOSE_EMAIL_VERIFICATION = 'email_verification';

    public const PURPOSE_MFA_LOGIN = 'mfa_login';

    protected $fillable = [
        'user_id',
        'purpose',
        'otp_hash',
        'attempt_count',
        'expires_at',
        'last_sent_at',
        'used_at',
    ];

    protected $hidden = ['otp_hash'];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
