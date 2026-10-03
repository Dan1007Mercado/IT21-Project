<?php

namespace Tests\Feature;

use App\Models\BlockedIp;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalSecurityEventApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['intsec.api_token' => 'test-intsec-token', 'intsec.event_sources' => ['hotel-booking']]);
    }

    public function test_authenticated_source_can_submit_an_event_and_the_server_classifies_it(): void
    {
        $this->withToken('test-intsec-token')->postJson('/api/security/events', $this->payload(['severity' => 'critical']))
            ->assertCreated()->assertJsonPath('data.severity', 'Warning');

        $this->assertDatabaseHas('security_events', [
            'source' => 'hotel-booking', 'event_type' => 'login_failed', 'severity' => 'Warning', 'source_ip' => '203.0.113.20',
        ]);
    }

    public function test_invalid_token_cannot_create_an_event(): void
    {
        $this->withToken('wrong')->postJson('/api/security/events', $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('security_events', 0);
    }

    public function test_repeated_external_failed_logins_create_one_alert(): void
    {
        foreach (range(1, 5) as $number) {
            $this->withToken('test-intsec-token')->postJson('/api/security/events', $this->payload(['event_id' => "00000000-0000-4000-8000-00000000000{$number}"]))->assertCreated();
        }

        $this->assertDatabaseCount('security_events', 5);
        $this->assertDatabaseCount('security_alerts', 1);
        $this->assertSame(SecurityAlert::TYPE_BRUTE_FORCE, SecurityAlert::query()->value('alert_type'));
    }

    public function test_event_id_makes_retries_idempotent(): void
    {
        $payload = $this->payload();
        $this->withToken('test-intsec-token')->postJson('/api/security/events', $payload)->assertCreated();
        $this->withToken('test-intsec-token')->postJson('/api/security/events', $payload)->assertOk()->assertJsonPath('data.duplicate', true);
        $this->assertDatabaseCount('security_events', 1);
    }

    public function test_event_ids_are_deduplicated_within_each_authenticated_source(): void
    {
        config(['intsec.event_sources' => ['hotel-booking', 'second-app']]);
        $payload = $this->payload();
        $this->withToken('test-intsec-token')->postJson('/api/security/events', $payload)->assertCreated();
        $this->withToken('test-intsec-token')->postJson('/api/security/events', array_merge($payload, ['source' => 'second-app']))->assertCreated();
        $this->assertDatabaseCount('security_events', 2);
    }

    public function test_monitored_decoy_login_creates_a_high_actionable_alert(): void
    {
        $this->withToken('test-intsec-token')->postJson('/api/security/events', $this->payload([
            'event_type' => 'monitored_login_attempt',
            'route' => '/security/monitored-login',
        ]))->assertCreated();

        $this->assertDatabaseHas('security_alerts', [
            'rule_key' => 'external.decoy_access',
            'severity' => 'High',
        ]);
    }

    public function test_authenticated_client_can_retrieve_current_active_blocks(): void
    {
        BlockedIp::query()->create(['ip_address' => '203.0.113.30', 'action' => 'block', 'is_enabled' => true, 'source' => 'manual', 'status' => 'active', 'blocked_at' => now()]);
        $this->withToken('test-intsec-token')->getJson('/api/security/blocked-ips')->assertOk()->assertJsonPath('data.0.ip_address', '203.0.113.30');
    }

    public function test_sql_injection_event_is_authoritatively_classified_alerted_deduplicated_and_correlated(): void
    {
        $payload = $this->payload([
            'event_type' => 'sql_injection_attempt',
            'severity' => 'normal',
            'route' => '/rooms',
            'message' => 'A request matched a contextual SQL injection detection rule.',
            'metadata' => [
                'request_id' => '00000000-0000-4000-8000-000000000099',
                'parameter' => 'search',
                'rule' => 'SQLI_UNION_SELECT',
                'confidence' => 0.98,
            ],
        ]);

        $this->withToken('test-intsec-token')->postJson('/api/security/events', $payload)
            ->assertCreated()->assertJsonPath('data.severity', 'High');
        $this->withToken('test-intsec-token')->postJson('/api/security/events', array_merge($payload, [
            'event_id' => '00000000-0000-4000-8000-000000000098',
        ]))->assertCreated();

        $this->assertDatabaseHas('security_events', [
            'source' => 'hotel-booking', 'event_type' => 'sql_injection_attempt',
            'rule_key' => 'external.sqli.sqli_union_select', 'severity' => 'High',
        ]);
        $alert = SecurityAlert::query()->where('alert_type', SecurityAlert::TYPE_SQL_INJECTION)->firstOrFail();
        $this->assertSame(2, $alert->occurrence_count);
        $this->assertNotNull($alert->incident_id);
        $this->assertDatabaseHas('incidents', ['id' => $alert->incident_id, 'incident_type' => 'sql_injection_activity']);
    }

    public function test_later_request_telemetry_is_linked_to_the_sql_injection_event_by_request_id(): void
    {
        $requestId = '00000000-0000-4000-8000-000000000097';
        $this->withToken('test-intsec-token')->postJson('/api/security/events', $this->payload([
            'event_type' => 'sql_injection_attempt',
            'route' => '/rooms',
            'metadata' => ['request_id' => $requestId, 'parameter' => 'search', 'rule' => 'SQLI_TAUTOLOGY', 'confidence' => 0.94],
        ]))->assertCreated();

        $this->withToken('test-intsec-token')->postJson('/api/security/request-activities', [
            'request_id' => $requestId,
            'source' => 'hotel-booking',
            'ip' => '203.0.113.20',
            'method' => 'GET',
            'path' => '/rooms',
            'route_name' => 'rooms.index',
            'status_code' => 200,
            'is_authenticated' => false,
            'metadata' => [],
        ])->assertCreated();

        $this->assertNotNull(SecurityEvent::query()->where('event_type', 'sql_injection_attempt')->value('request_activity_id'));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'event_id' => '00000000-0000-4000-8000-000000000001', 'source' => 'hotel-booking', 'event_type' => 'login_failed',
            'severity' => 'normal', 'ip' => '203.0.113.20', 'route' => '/login', 'method' => 'POST',
            'user_agent' => 'INTSEC test', 'message' => 'Failed authentication attempt', 'metadata' => [],
        ], $overrides);
    }
}
