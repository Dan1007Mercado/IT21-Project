<?php

namespace Database\Factories;

use App\Models\AuthenticationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthenticationLog>
 */
class AuthenticationLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'attempted_identity' => fake()->safeEmail(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'action' => fake()->randomElement(['login', 'logout']),
            'status' => fake()->randomElement(['successful', 'failed']),
            'failure_reason' => null,
            'route' => '/login',
            'method' => 'POST',
            'occurred_at' => fake()->dateTimeBetween('-14 days', 'now'),
        ];
    }
}
