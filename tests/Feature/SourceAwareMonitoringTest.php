<?php

namespace Tests\Feature;

use App\Models\AuthenticationLog;
use App\Models\Incident;
use App\Models\RequestActivity;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Security\RequestDetectionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
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

    public function test_monitoring_overview_kpis_use_current_period_data_and_remain_source_isolated(): void
    {
        CarbonImmutable::setTestNow('2026-10-05 12:00:00');
        $admin = User::factory()->administrator()->create();

        RequestActivity::factory()->create([
            'source' => 'hotel-booking', 'ip_address' => '2001:4860:4860::8888',
            'path' => '/hotel-current-one', 'occurred_at' => now()->subHours(2),
        ]);
        RequestActivity::factory()->create([
            'source' => 'hotel-booking', 'ip_address' => '8.8.8.8',
            'path' => '/hotel-current-two', 'occurred_at' => now()->subHours(3),
        ]);
        RequestActivity::factory()->create([
            'source' => 'hotel-booking', 'ip_address' => '1.1.1.1',
            'path' => '/hotel-previous', 'occurred_at' => now()->subHours(30),
        ]);
        RequestActivity::factory()->count(4)->create([
            'source' => 'intsec', 'path' => '/intsec-current', 'occurred_at' => now()->subHour(),
        ]);

        AuthenticationLog::factory()->create([
            'source' => 'hotel-booking', 'action' => 'login', 'status' => 'successful',
            'occurred_at' => now()->subHours(4),
        ]);
        AuthenticationLog::factory()->create([
            'source' => 'hotel-booking', 'action' => 'login', 'status' => 'failed',
            'occurred_at' => now()->subHours(5),
        ]);
        AuthenticationLog::factory()->create([
            'source' => 'intsec', 'action' => 'login', 'status' => 'failed',
            'occurred_at' => now()->subHours(2),
        ]);

        SecurityEvent::query()->create([
            'title' => 'Hotel event only', 'source' => 'hotel-booking', 'event_type' => 'test',
            'severity' => 'High', 'status' => 'new', 'occurred_at' => now()->subHour(),
        ]);
        SecurityEvent::query()->create([
            'title' => 'INTSEC event only', 'source' => 'intsec', 'event_type' => 'test',
            'severity' => 'Critical', 'status' => 'new', 'occurred_at' => now()->subHour(),
        ]);
        SecurityAlert::query()->create([
            'alert_id' => 'ALT-2026-920001', 'source' => 'hotel-booking', 'title' => 'Hotel alert',
            'alert_type' => 'test', 'severity' => 'Critical', 'status' => 'new', 'occurred_at' => now(),
        ]);
        Incident::query()->create([
            'source' => 'hotel-booking', 'title' => 'Hotel incident', 'incident_type' => 'test',
            'severity' => 'High', 'status' => 'open', 'event_count' => 1,
            'first_detected_at' => now(), 'last_detected_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/monitoring/hotel-booking');
        $metrics = collect($response->viewData('kpis'))->keyBy('label');

        $response->assertOk()
            ->assertSee('Hotel event only')
            ->assertDontSee('INTSEC event only')
            ->assertDontSee('/intsec-current');
        $this->assertSame(2, $metrics['Requests']['value']);
        $this->assertSame(100.0, $metrics['Requests']['trend']['percentage']);
        $this->assertSame(2, $metrics['Unique IPs']['value']);
        $this->assertSame(2, $metrics['Authentication attempts']['value']);
        $this->assertSame(50.0, $metrics['Authentication attempts']['rate']);
        $this->assertSame(1, $metrics['Security events']['value']);
        $this->assertSame(1, $metrics['Open alerts']['value']);
        $this->assertSame(1, $metrics['Open incidents']['value']);
        $this->assertCount(24, $response->viewData('requestTrend'));

        CarbonImmutable::setTestNow();
    }

    public function test_monitoring_overview_uses_neutral_comparisons_when_the_previous_period_is_zero(): void
    {
        CarbonImmutable::setTestNow('2026-10-05 12:00:00');
        $admin = User::factory()->administrator()->create();
        RequestActivity::factory()->create([
            'source' => 'hotel-booking', 'occurred_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($admin)->get('/monitoring/hotel-booking');
        $metrics = collect($response->viewData('kpis'))->keyBy('label');

        $response->assertOk();
        $this->assertNull($metrics['Requests']['trend']['percentage']);
        $this->assertSame('New activity vs previous 24h', $metrics['Requests']['trend']['label']);
        $this->assertNull($metrics['Authentication attempts']['rate']);
        $this->assertSame('No activity in either 24h period', $metrics['Authentication attempts']['trend']['label']);

        CarbonImmutable::setTestNow();
    }

    public function test_detection_thresholds_and_alerts_are_isolated_by_source(): void
    {
        SystemSetting::query()->create(['key' => 'repeated_404_threshold', 'value' => '2']);

        foreach (['intsec', 'hotel-booking'] as $source) {
            RequestActivity::factory()->create(['source' => $source, 'ip_address' => '8.8.8.8', 'status_code' => 404, 'occurred_at' => now()]);
        }

        app(RequestDetectionService::class)->evaluate(RequestActivity::query()->forSource('hotel-booking')->firstOrFail());
        $this->assertDatabaseCount('security_alerts', 0);

        $second = RequestActivity::factory()->create(['source' => 'hotel-booking', 'ip_address' => '8.8.8.8', 'status_code' => 404, 'occurred_at' => now()]);
        app(RequestDetectionService::class)->evaluate($second);

        $this->assertSame('hotel-booking', SecurityAlert::query()->sole()->source);
    }

    public function test_request_listing_uses_ten_row_simple_pagination_search_and_preserves_filters(): void
    {
        $admin = User::factory()->administrator()->create();
        RequestActivity::factory()->count(12)->create([
            'source' => 'hotel-booking', 'path' => '/hotel-reservations', 'method' => 'POST',
        ]);
        RequestActivity::factory()->create(['source' => 'intsec', 'path' => '/hotel-reservations', 'method' => 'POST']);

        $response = $this->actingAs($admin)->get('/monitoring/hotel-booking/request-activity?search=hotel&method=POST');
        $activities = $response->viewData('activities');

        $response->assertOk()->assertSee('/hotel-reservations');
        $this->assertInstanceOf(Paginator::class, $activities);
        $this->assertCount(10, $activities->items());
        $this->assertStringContainsString('search=hotel', $activities->nextPageUrl());
        $this->assertStringContainsString('method=POST', $activities->nextPageUrl());
        $this->assertTrue($activities->getCollection()->every(fn (RequestActivity $activity): bool => $activity->source === 'hotel-booking'));
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
