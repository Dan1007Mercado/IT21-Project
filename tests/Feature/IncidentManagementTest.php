<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\AuthenticationLog;
use App\Services\Security\IntsecSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_incident_dashboard_and_list(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get('/incidents')
            ->assertOk()
            ->assertSee('Incident management')
            ->assertSee('Open incidents');

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Incident management');
    }

    public function test_standard_user_cannot_access_incident_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/incidents')
            ->assertForbidden();
    }

    public function test_administrator_can_create_incident_and_add_remark_and_change_status(): void
    {
        $admin = User::factory()->administrator()->create();
        $targetUser = User::factory()->create();

        $securityEvent = SecurityEvent::query()->create([
            'title' => 'Repeated failed authentication',
            'event_type' => 'failed_login',
            'severity' => 'High',
            'description' => 'Multiple failed login attempts from a single IP.',
            'user_id' => $targetUser->id,
            'source_ip' => '203.0.113.10',
            'metadata' => ['attempt_count' => 8],
            'status' => 'new',
            'occurred_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($admin)
            ->post('/incidents', [
                'title' => 'Suspicious repeated login failures',
                'description' => 'The same source IP exceeded the failed login threshold.',
                'incident_type' => 'authentication',
                'severity' => 'High',
                'source_ip' => '203.0.113.10',
                'user_id' => $targetUser->id,
                'security_event_id' => $securityEvent->id,
                'status' => 'open',
                'assigned_to' => $admin->id,
                'detection_reason' => 'Threshold exceeded for repeated failed login events.',
                'detection_rule' => 'failed_login_threshold',
                'event_count' => 8,
                'first_detected_at' => now()->subMinutes(10)->toDateTimeString(),
                'last_detected_at' => now()->subMinutes(5)->toDateTimeString(),
            ])
            ->assertRedirect();

        $incident = Incident::query()->firstOrFail();

        $this->assertSame('INC-'.now()->format('Y').'-000001', $incident->incident_id);
        $this->assertDatabaseHas('incident_remarks', [
            'incident_id' => $incident->id,
            'author_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post('/incidents/'.$incident->id.'/remarks', [
                'remark' => 'Initial investigation is underway and the IP is under review.',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch('/incidents/'.$incident->id.'/status', [
                'status' => 'investigating',
                'reason' => 'Reviewing failed-sign-in patterns for the targeted account.',
            ])
            ->assertRedirect();

        $incident->refresh();
        $this->assertSame('investigating', $incident->status);
        $this->assertDatabaseHas('incident_status_histories', [
            'incident_id' => $incident->id,
            'new_status' => 'investigating',
        ]);
    }

    public function test_ip_activity_threshold_creates_alert_and_incident_only_when_exceeded(): void
    {
        $admin = User::factory()->administrator()->create();
        IntsecSettings::set('repeated_ip_activity_threshold', 30);

        AuthenticationLog::factory()
            ->count(10)
            ->create([
                'ip_address' => '203.0.113.30',
                'occurred_at' => now()->subMinutes(5),
            ]);

        $this->actingAs($admin)->get('/attack-frequency')->assertOk();

        $this->assertDatabaseCount('security_events', 0);
        $this->assertDatabaseCount('security_alerts', 0);
        $this->assertDatabaseCount('incidents', 0);

        AuthenticationLog::factory()
            ->count(20)
            ->create([
                'ip_address' => '203.0.113.30',
                'occurred_at' => now()->subMinutes(5),
            ]);

        $this->actingAs($admin)->get('/attack-frequency')->assertOk();

        $this->assertDatabaseCount('security_events', 0);
        $this->assertDatabaseCount('security_alerts', 0);
        $this->assertDatabaseCount('incidents', 0);

        AuthenticationLog::factory()->create([
            'ip_address' => '203.0.113.30',
            'occurred_at' => now()->subMinutes(4),
        ]);

        $this->actingAs($admin)->get('/attack-frequency')->assertOk();

        $this->assertDatabaseCount('security_events', 1);
        $this->assertDatabaseCount('security_alerts', 1);
        $this->assertDatabaseCount('incidents', 1);

        $incident = Incident::query()->firstOrFail();
        $alert = SecurityAlert::query()->firstOrFail();

        $this->assertSame($incident->id, $alert->incident_id);
        $this->assertSame('203.0.113.30', $incident->source_ip);
        $this->assertSame(31, $incident->event_count);
        $this->assertSame(SecurityAlert::TYPE_REPEATED_IP_ACTIVITY, $alert->alert_type);
        $this->assertSame('repeated_ip_activity_threshold', $alert->metadata['detection_rule']);

        $this->actingAs($admin)
            ->get('/incidents')
            ->assertOk()
            ->assertSee($incident->incident_id)
            ->assertSee('Request spike detected from 203.0.113.30');

        $this->actingAs($admin)
            ->get('/incidents/'.$incident->id)
            ->assertOk()
            ->assertSee('Request/IP activity threshold exceeded')
            ->assertSee('31');
    }

    public function test_ip_activity_monitoring_deduplicates_same_event_window_and_respects_setting_changes(): void
    {
        $admin = User::factory()->administrator()->create();
        IntsecSettings::set('repeated_ip_activity_threshold', 30);

        AuthenticationLog::factory()
            ->count(31)
            ->create([
                'ip_address' => '203.0.113.31',
                'occurred_at' => now()->subMinutes(5),
            ]);

        $this->actingAs($admin)->get('/attack-frequency')->assertOk();
        $this->actingAs($admin)->get('/attack-frequency')->assertOk();

        $this->assertDatabaseCount('security_events', 1);
        $this->assertDatabaseCount('security_alerts', 1);
        $this->assertDatabaseCount('incidents', 1);

        AuthenticationLog::factory()
            ->count(5)
            ->create([
                'ip_address' => '203.0.113.31',
                'occurred_at' => now()->subMinutes(2),
            ]);

        $this->actingAs($admin)->get('/attack-frequency')->assertOk();

        $this->assertDatabaseCount('security_events', 1);
        $this->assertDatabaseCount('security_alerts', 1);
        $this->assertDatabaseCount('incidents', 1);
        $this->assertSame(36, Incident::query()->firstOrFail()->event_count);

        IntsecSettings::set('repeated_ip_activity_threshold', 50);

        AuthenticationLog::factory()
            ->count(50)
            ->create([
                'ip_address' => '203.0.113.50',
                'occurred_at' => now()->subMinutes(3),
            ]);

        $this->actingAs($admin)->get('/attack-frequency')->assertOk();

        $this->assertDatabaseCount('incidents', 1);

        AuthenticationLog::factory()->create([
            'ip_address' => '203.0.113.50',
            'occurred_at' => now()->subMinute(),
        ]);

        $this->actingAs($admin)->get('/attack-frequency')->assertOk();

        $this->assertDatabaseCount('security_events', 2);
        $this->assertDatabaseCount('security_alerts', 2);
        $this->assertDatabaseCount('incidents', 2);
    }
}
