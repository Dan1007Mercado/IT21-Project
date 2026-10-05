<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
        'pending_totp_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'totp_secret' => 'encrypted',
            'pending_totp_secret' => 'encrypted',
            'totp_confirmed_at' => 'datetime',
            'mfa_enabled_at' => 'datetime',
            'recovery_codes_confirmed_at' => 'datetime',
            'last_totp_timestep' => 'integer',
        ];
    }

    public function authenticationLogs(): HasMany
    {
        return $this->hasMany(AuthenticationLog::class);
    }

    public function requestActivities(): HasMany
    {
        return $this->hasMany(RequestActivity::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function mfaOtpChallenges(): HasMany
    {
        return $this->hasMany(MfaOtpChallenge::class);
    }

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(UserRecoveryCode::class);
    }

    public function hasMfaConfigured(): bool
    {
        return $this->mfa_enabled_at !== null
            && $this->totp_confirmed_at !== null
            && $this->recovery_codes_confirmed_at !== null
            && filled($this->totp_secret);
    }

    public function assignedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'assigned_to');
    }

    public function incidentRemarks(): HasMany
    {
        return $this->hasMany(IncidentRemark::class, 'author_id');
    }

    public function isAdministrator(): bool
    {
        return $this->role === 'administrator';
    }
}
