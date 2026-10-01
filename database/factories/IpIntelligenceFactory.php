<?php

namespace Database\Factories;

use App\Models\IpIntelligence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IpIntelligence> */
class IpIntelligenceFactory extends Factory
{
    protected $model = IpIntelligence::class;

    public function definition(): array
    {
        return [
            'ip_address' => fake()->unique()->ipv4(),
            'ip_type' => 'public',
            'country' => fake()->country(),
            'country_code' => fake()->countryCode(),
            'region' => fake()->state(),
            'city' => fake()->city(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'timezone' => fake()->timezone(),
            'asn' => fake()->numberBetween(1, 4294967295),
            'isp' => fake()->company(),
            'organization' => fake()->company(),
            'provider' => 'factory',
            'last_enriched_at' => now(),
            'last_seen_at' => now(),
            'metadata' => [],
        ];
    }
}
