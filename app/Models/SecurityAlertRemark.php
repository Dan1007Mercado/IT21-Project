<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAlertRemark extends Model
{
    protected $fillable = [
        'security_alert_id',
        'author_id',
        'remark',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(SecurityAlert::class, 'security_alert_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
