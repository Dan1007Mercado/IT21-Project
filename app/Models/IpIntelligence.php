<?php

namespace App\Models;

use Database\Factories\IpIntelligenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpIntelligence extends Model
{
    /** @use HasFactory<IpIntelligenceFactory> */
    use HasFactory;

    protected $fillable = [
        'ip_address', 'ip_type', 'country', 'country_code', 'region', 'region_code',
        'city', 'latitude', 'longitude', 'postal', 'timezone', 'asn', 'isp',
        'organization', 'provider', 'last_enriched_at', 'last_seen_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'asn' => 'integer',
            'last_enriched_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null
            && ! ($this->latitude === 0.0 && $this->longitude === 0.0);
    }
}
