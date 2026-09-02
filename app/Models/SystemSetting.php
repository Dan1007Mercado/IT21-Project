<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('system_settings')) {
            return $default;
        }

        try {
            $value = static::query()->where('key', $key)->value('value');
        } catch (QueryException $exception) {
            return $default;
        }

        if ($value === null) {
            return $default;
        }

        return $value;
    }
}
