<?php

namespace Database\Seeders;

use App\Models\AuthenticationLog;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaults = [
            'max_login_attempts' => '5',
            'login_attempt_window_minutes' => '5',
            'login_block_duration_minutes' => '15',
            'failed_login_warning_threshold' => '3',
            'repeated_authentication_threshold' => '5',
            'repeated_ip_activity_threshold' => '10',
            'default_ip_block_duration_minutes' => '60',
        ];

        foreach ($defaults as $key => $value) {
            \App\Models\SystemSetting::query()->updateOrCreate([
                'key' => $key,
            ], [
                'value' => (string) $value,
                'description' => $key,
            ]);
        }

        $admin = User::query()->updateOrCreate([
            'email' => 'admin@intsec.test',
        ], [
            'name' => 'INTSEC Administrator',
            'password' => Hash::make('password'),
            'role' => 'administrator',
            'is_active' => true,
        ]);

        $user = User::query()->updateOrCreate([
            'email' => 'user@intsec.test',
        ], [
            'name' => 'Standard User',
            'password' => Hash::make('password'),
            'role' => 'standard_user',
            'is_active' => true,
        ]);

        User::factory(8)->create();

        $demoLocations = [
            [
                'ip_address' => '8.8.8.8',
                'country' => 'United States',
                'country_code' => 'US',
                'region' => 'California',
                'region_code' => 'CA',
                'city' => 'Mountain View',
                'latitude' => 37.4056,
                'longitude' => -122.0775,
                'postal' => '94043',
                'isp' => 'Google LLC',
                'organization' => 'Google LLC',
                'asn' => 15169,
                'timezone' => 'America/Los_Angeles',
            ],
            [
                'ip_address' => '1.1.1.1',
                'country' => 'Australia',
                'country_code' => 'AU',
                'region' => 'New South Wales',
                'region_code' => 'NSW',
                'city' => 'Sydney',
                'latitude' => -33.8688,
                'longitude' => 151.2093,
                'postal' => '2000',
                'isp' => 'Cloudflare, Inc.',
                'organization' => 'Cloudflare, Inc.',
                'asn' => 13335,
                'timezone' => 'Australia/Sydney',
            ],
            [
                'ip_address' => '208.67.222.222',
                'country' => 'United States',
                'country_code' => 'US',
                'region' => 'Florida',
                'region_code' => 'FL',
                'city' => 'Miami',
                'latitude' => 25.7617,
                'longitude' => -80.1918,
                'postal' => '33101',
                'isp' => 'OpenDNS',
                'organization' => 'Cisco',
                'asn' => 36692,
                'timezone' => 'America/New_York',
            ],
        ];

        foreach ($demoLocations as $index => $location) {
            AuthenticationLog::factory()->create([
                'user_id' => $admin->id,
                'attempted_identity' => 'demo.public.ip'.($index + 1).'@intsec.test',
                'ip_address' => $location['ip_address'],
                'country' => $location['country'],
                'country_code' => $location['country_code'],
                'region' => $location['region'],
                'region_code' => $location['region_code'],
                'city' => $location['city'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'postal' => $location['postal'],
                'isp' => $location['isp'],
                'organization' => $location['organization'],
                'asn' => $location['asn'],
                'timezone' => $location['timezone'],
                'action' => 'login',
                'status' => 'successful',
                'failure_reason' => null,
                'route' => '/demo/ip-intelligence',
                'method' => 'GET',
                'occurred_at' => now()->subHours(12 + $index),
            ]);
        }

        AuthenticationLog::factory(10)->create();

        AuthenticationLog::factory()->create([
            'user_id' => $admin->id,
            'attempted_identity' => $admin->email,
            'action' => 'login',
            'status' => 'successful',
            'failure_reason' => null,
            'occurred_at' => now()->subMinutes(20),
        ]);

        AuthenticationLog::factory()->create([
            'user_id' => $user->id,
            'attempted_identity' => $user->email,
            'action' => 'login',
            'status' => 'successful',
            'failure_reason' => null,
            'occurred_at' => now()->subMinutes(10),
        ]);
    }
}
