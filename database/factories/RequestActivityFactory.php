<?php

namespace Database\Factories;

use App\Models\RequestActivity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<RequestActivity> */
class RequestActivityFactory extends Factory
{
    protected $model = RequestActivity::class;

    public function definition(): array
    {
        return [
            'request_id' => (string) Str::uuid(),
            'source' => 'local',
            'user_id' => null,
            'ip_address' => fake()->ipv4(),
            'ip_type' => 'public',
            'method' => fake()->randomElement(['GET', 'POST', 'PATCH']),
            'path' => '/'.fake()->slug(),
            'route_name' => null,
            'status_code' => fake()->randomElement([200, 200, 200, 404]),
            'user_agent' => fake()->userAgent(),
            'referer' => null,
            'is_authenticated' => false,
            'duration_ms' => fake()->numberBetween(2, 900),
            'request_size' => fake()->numberBetween(100, 4000),
            'response_size' => fake()->numberBetween(200, 10000),
            'classification' => 'normal',
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}
