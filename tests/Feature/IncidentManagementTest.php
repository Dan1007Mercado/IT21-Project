<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\SecurityEvent;
use App\Models\User;
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
}
