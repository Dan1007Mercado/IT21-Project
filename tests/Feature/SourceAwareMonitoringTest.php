<?php

namespace Tests\Feature;

use App\Models\AuthenticationLog;
use App\Models\RequestActivity;
use App\Models\SecurityAlert;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceAwareMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'intsec.api_token' => 'test-intsec-token',
            'intsec.event_sources' => ['hotel-booking', 'second-app'],
            'intsec.ip_intelligence.enabled' => false,
        ]);
    }

    public function test_external_request_activity_is_stored_in_shared_table_and_is_idempotent_per_source(): void
    {
        $payload = $this->requestPayload();

        $this->withToken('test-intsec-token')->postJson('/api/security/request-activities', $payload)
            ->assertCreated()->assertJsonPath('data.duplicate', false);
        $this->withToken('test-intsec-token')->postJson('/api/security/request-activities', $payload)
            ->assertOk()->assertJsonPath('data.duplicate', true);

        $this->assertDatabaseCount('request_activities', 1);
        $this->assertDatabaseHas('request_activities', [
            'source' => 'hotel-booking',
            'request_id' => $payload['request_id'],
            'path' => '/hotel',
            'status_code' => 200,
        ]);

        $activity = RequestActivity::query()->firstOrFail();
        $this->assertStringNotContainsString('must-not-persist', json_encode($activity->metadata));
        $this->assertSame('[REDACTED]', $activity->metadata['nested']['password']);
    }

    public function test_same_external_request_id_is_allowed_for_a_different_source(): void
    {
        $payload = $this->requestPayload();
        $this->withToken('test-intsec-token')->postJson('/api/security/request-activities', $payload)->assertCreated();
        $this->withToken('test-intsec-token')->postJson('/api/security/request-activities', array_merge($payload, ['source' => 'second-app']))->assertCreated();

        $this->assertDatabaseCount('request_activities', 2);
    }

    public function test_external_authentication_event_also_creates_source_aware_authentication_telemetry(): void
    {
        $this->withToken('test-intsec-token')->postJson('/api/security/events', [
            'event_id' => '00000000-0000-4000-8000-000000000020',
            'source' => 'hotel-booking',
            'event_type' => 'login_failed',
            'ip' => '8.8.8.8',
            'route' => '/login',
            'method' => 'POST',
            'user_agent' => 'Source test',
            'message' => 'Failed authentication attempt.',
            'metadata' => ['password' => 'must-not-persist'],
        ])->assertCreated();

        $this->assertDatabaseHas('authentication_logs', [
            'source' => 'hotel-booking',
            'user_id' => null,
            'action' => 'login',
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('security_events', ['source' => 'hotel-booking']);
    }

    public function test_source_specific_monitoring_pages_do_not_mix_rows(): void
    {
        $admin = User::factory()->administrator()->create();
        RequestActivity::factory()->create(['source' => 'intsec', 'path' => '/intsec-only']);
        RequestActivity::factory()->create(['source' => 'hotel-booking', 'path' => '/hotel-only']);
        AuthenticationLog::factory()->create(['source' => 'intsec', 'attempted_identity' => 'intsec@example.test']);
        AuthenticationLog::factory()->create(['source' => 'hotel-booking', 'attempted_identity' => 'hotel@example.test']);

        $this->actingAs($admin)->get('/monitoring/hotel-booking/request-activity')
            ->assertOk()->assertSee('/hotel-only')->assertDontSee('/intsec-only');
        $this->actingAs($admin)->get('/monitoring/intsec/login-activity')
            ->assertOk()->assertSee('intsec@example.test')->assertDontSee('hotel@example.test');
    }

    public function test_detection_thresholds_and_alerts_are_isolated_by_source(): void
    {
        SystemSetting::query()->create(['key' => 'repeated_404_threshold', 'value' => '2']);

        foreach (['intsec', 'hotel-booking'] as $source) {
            RequestActivity::factory()->create(['source' => $source, 'ip_address' => '8.8.8.8', 'status_code' => 404, 'occurred_at' => now()]);
        }

        app(\App\Services\Security\RequestDetectionService::class)->evaluate(RequestActivity::query()->forSource('hotel-booking')->firstOrFail());
        $this->assertDatabaseCount('security_alerts', 0);

        $second = RequestActivity::factory()->create(['source' => 'hotel-booking', 'ip_address' => '8.8.8.8', 'status_code' => 404, 'occurred_at' => now()]);
        app(\App\Services\Security\RequestDetectionService::class)->evaluate($second);

        $this->assertSame('hotel-booking', SecurityAlert::query()->sole()->source);
    }

    /** @return array<string, mixed> */
    private function requestPayload(): array
    {
        return [
            'request_id' => '00000000-0000-4000-8000-000000000010',
            'source' => 'hotel-booking',
            'ip' => '8.8.4.4',
            'method' => 'GET',
            'path' => '/hotel',
            'route_name' => 'guest.home',
            'status_code' => 200,
            'user_agent' => 'Source test',
            'is_authenticated' => false,
            'duration_ms' => 12,
            'metadata' => ['nested' => ['password' => 'must-not-persist']],
        ];
    }
}
