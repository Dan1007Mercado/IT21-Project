<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRecoveryCode extends Model
{
    protected $fillable = ['user_id', 'generation_id', 'code_hash', 'consumed_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['consumed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
