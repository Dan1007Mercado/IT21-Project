<?php

namespace Tests\Feature;

use App\Models\AuthenticationLog;
use App\Models\IpIntelligence;
use App\Models\RequestActivity;
use App\Models\SecurityAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
        ]);
    }

    public function test_login_page_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('test-site-key')
            ->assertDontSee('test-secret-key');
    }

    public function test_user_can_login_and_authentication_activity_is_logged(): void
    {
        $this->fakeSuccessfulRecaptcha();

        $user = User::factory()->create([
            'email' => 'user@intsec.test',
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'email' => 'user@intsec.test',
            'password' => 'password',
            'g-recaptcha-response' => 'valid-response-token',
        ])->assertRedirect('/mfa/challenge');

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('authentication_logs', [
            'user_id' => $user->id,
            'attempted_identity' => 'user@intsec.test',
            'action' => 'login_first_factor',
            'status' => 'successful',
            'failure_reason' => null,
        ]);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
            && $request['secret'] === 'test-secret-key'
            && $request['response'] === 'valid-response-token');
    }

    public function test_failed_login_is_logged_without_authenticating_user(): void
    {
        $this->fakeSuccessfulRecaptcha();

        $user = User::factory()->create([
            'email' => 'user@intsec.test',
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'email' => 'user@intsec.test',
            'password' => 'wrong-password',
            'g-recaptcha-response' => 'valid-response-token',
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
        $this->fakeSuccessfulRecaptcha();

        $user = User::factory()->inactive()->create([
            'email' => 'disabled@intsec.test',
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'email' => 'disabled@intsec.test',
            'password' => 'password',
            'g-recaptcha-response' => 'valid-response-token',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->assertDatabaseHas('authentication_logs', [
            'user_id' => $user->id,
            'status' => 'failed',
            'failure_reason' => 'account_disabled',
        ]);
    }

    public function test_login_requires_a_recaptcha_response_before_authentication(): void
    {
        User::factory()->create([
            'email' => 'user@intsec.test',
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'email' => 'user@intsec.test',
            'password' => 'password',
        ])->assertSessionHasErrors('g-recaptcha-response');

        $this->assertGuest();
        $this->assertDatabaseCount('authentication_logs', 0);
        Http::assertNothingSent();
    }

    public function test_invalid_recaptcha_response_rejects_valid_credentials(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ]),
        ]);

        User::factory()->create([
            'email' => 'user@intsec.test',
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'email' => 'user@intsec.test',
            'password' => 'password',
            'g-recaptcha-response' => 'invalid-response-token',
        ])->assertSessionHasErrors('g-recaptcha-response');

        $this->assertGuest();
        $this->assertDatabaseCount('authentication_logs', 0);
    }

    public function test_recaptcha_service_failure_is_fail_closed(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([], 503),
        ]);

        User::factory()->create([
            'email' => 'user@intsec.test',
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'email' => 'user@intsec.test',
            'password' => 'password',
            'g-recaptcha-response' => 'response-token',
        ])->assertSessionHasErrors('g-recaptcha-response');

        $this->assertGuest();
    }

    public function test_missing_recaptcha_configuration_is_fail_closed_without_an_outbound_request(): void
    {
        config(['services.recaptcha.secret_key' => null]);

        User::factory()->create([
            'email' => 'user@intsec.test',
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'email' => 'user@intsec.test',
            'password' => 'password',
            'g-recaptcha-response' => 'response-token',
        ])->assertSessionHasErrors('g-recaptcha-response');

        $this->assertGuest();
        Http::assertNothingSent();
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
            ->assertSee('Security overview');

        $this->actingAs($user)->get('/ip-locations')
            ->assertOk()
            ->assertSee('IP locations');

        $this->actingAs($user)->get('/ddos-monitoring')
            ->assertOk()
            ->assertSee('Application request spikes');

        $this->actingAs($user)->get('/attack-frequency')
            ->assertOk()
            ->assertSee('IP Request Frequency');

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
            ->assertSee('Request activity trend')
            ->assertSee('Authentication activity trend');
    }

    public function test_administrator_dashboard_displays_open_security_alert_notifications(): void
    {
        $admin = User::factory()->administrator()->create();
        SecurityAlert::query()->create([
            'alert_id' => 'ALT-2026-000777',
            'title' => 'Brute-force login threshold exceeded',
            'alert_type' => SecurityAlert::TYPE_BRUTE_FORCE,
            'severity' => 'High',
            'source_ip' => '203.0.113.77',
            'status' => 'new',
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Recent important alerts')
            ->assertSee('Brute-force login threshold exceeded')
            ->assertSee('203.0.113.77');
    }

    public function test_ip_locations_page_handles_public_geolocated_ips(): void
    {
        $user = User::factory()->create();

        IpIntelligence::query()->create([
            'ip_address' => '8.8.8.8',
            'ip_type' => 'public',
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
            'last_seen_at' => now()->subMinutes(5),
        ]);
        RequestActivity::factory()->create(['user_id' => $user->id, 'ip_address' => '8.8.8.8', 'ip_type' => 'public']);

        $this->actingAs($user)
            ->get('/ip-locations')
            ->assertOk()
            ->assertSee('IP locations')
            ->assertSee('Approximate public-IP enrichment');
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', [
            'name' => 'Updated User',
            'email' => 'updated@intsec.test',
            'current_password' => 'password',
        ])->assertRedirect();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'password',
            'password' => 'New-password-123',
            'password_confirmation' => 'New-password-123',
        ])->assertRedirect();

        $user->refresh();

        $this->assertSame('Updated User', $user->name);
        $this->assertSame('updated@intsec.test', $user->email);
        $this->assertTrue(Hash::check('New-password-123', $user->password));
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

    private function fakeSuccessfulRecaptcha(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);
    }
}
