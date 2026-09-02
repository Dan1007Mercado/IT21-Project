<?php

namespace Tests\Feature;

use App\Models\AuthenticationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in');
    }

    public function test_user_can_login_and_authentication_activity_is_logged(): void
    {
        $user = User::factory()->create([
            'email' => 'user@intsec.test',
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'email' => 'user@intsec.test',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('authentication_logs', [
            'user_id' => $user->id,
            'attempted_identity' => 'user@intsec.test',
            'action' => 'login',
            'status' => 'successful',
            'failure_reason' => null,
        ]);
    }

    public function test_failed_login_is_logged_without_authenticating_user(): void
    {
        $user = User::factory()->create([
            'email' => 'user@intsec.test',
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'email' => 'user@intsec.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->assertDatabaseHas('authentication_logs', [
            'user_id' => $user->id,
            'attempted_identity' => 'user@intsec.test',
            'action' => 'login',
            'status' => 'failed',
            'failure_reason' => 'invalid_credentials',
        ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'disabled@intsec.test',
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'email' => 'disabled@intsec.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->assertDatabaseHas('authentication_logs', [
            'user_id' => $user->id,
            'status' => 'failed',
            'failure_reason' => 'account_disabled',
        ]);
    }

    public function test_authenticated_user_can_view_dashboard_and_monitoring_pages(): void
    {
        $user = User::factory()->create();
        AuthenticationLog::factory()->create([
            'user_id' => $user->id,
            'attempted_identity' => $user->email,
            'action' => 'login',
            'status' => 'successful',
            'occurred_at' => now(),
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('User dashboard');

        $this->actingAs($user)->get('/ip-locations')
            ->assertOk()
            ->assertSee('IP locations');

        $this->actingAs($user)->get('/ddos-monitoring')
            ->assertOk()
            ->assertSee('DDoS / request spikes');

        $this->actingAs($user)->get('/attack-frequency')
            ->assertOk()
            ->assertSee('Attack frequency');

        $this->actingAs($user)->get('/login-activity')
            ->assertOk()
            ->assertSee('Login activity');
    }

    public function test_dashboard_renders_activity_charts(): void
    {
        $user = User::factory()->create();

        AuthenticationLog::factory()->count(6)->create([
            'user_id' => $user->id,
            'occurred_at' => now()->subDays(2),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('7-day activity trend')
            ->assertSee('Login status mix');
    }

    public function test_ip_locations_page_handles_public_geolocated_ips(): void
    {
        $user = User::factory()->create();

        AuthenticationLog::factory()->create([
            'user_id' => $user->id,
            'ip_address' => '8.8.8.8',
            'country' => 'United States',
            'country_code' => 'US',
            'region' => 'California',
            'region_code' => 'CA',
            'city' => 'Mountain View',
            'latitude' => 37.4056,
            'longitude' => -122.0775,
            'isp' => 'Google LLC',
            'organization' => 'Google LLC',
            'asn' => 15169,
            'timezone' => 'America/Los_Angeles',
            'occurred_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($user)
            ->get('/ip-locations')
            ->assertOk()
            ->assertSee('IP locations')
            ->assertSee('Approximate geographic locations');
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', [
            'name' => 'Updated User',
            'email' => 'updated@intsec.test',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect();

        $user->refresh();

        $this->assertSame('Updated User', $user->name);
        $this->assertSame('updated@intsec.test', $user->email);
        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    public function test_logout_records_activity_and_ends_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();

        $this->assertDatabaseHas('authentication_logs', [
            'user_id' => $user->id,
            'attempted_identity' => $user->email,
            'action' => 'logout',
            'status' => 'successful',
        ]);
    }

    public function test_standard_user_cannot_access_admin_area(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')
            ->assertForbidden();
    }

    public function test_administrator_can_access_admin_area(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Security operations workspace');
    }
}
