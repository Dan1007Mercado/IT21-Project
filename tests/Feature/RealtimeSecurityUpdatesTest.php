<?php

namespace Tests\Feature;

use App\Events\SecurityStateChanged;
use App\Models\AuthenticationLog;
use App\Models\BlockedIp;
use App\Models\Incident;
use App\Models\RequestActivity;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RealtimeSecurityUpdatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_model_changes_dispatch_a_minimal_source_aware_private_event(): void
    {
        Event::fake([SecurityStateChanged::class]);
        RequestActivity::factory()->create([
            'source' => 'hotel-booking',
            'path' => '/reservations',
            'user_agent' => 'private browser detail',
        ]);

        Event::assertDispatched(SecurityStateChanged::class, function (SecurityStateChanged $event): bool {
            $channel = $event->broadcastOn()[0];

            return $event->entity === 'request_activity'
                && $event->action === 'created'
                && $event->source === 'hotel-booking'
                && $event->record['path'] === '/reservations'
                && ! array_key_exists('user_agent', $event->record)
                && $event->connection === 'sync'
                && $channel instanceof PrivateChannel
                && $channel->name === 'private-intsec.security';
        });
    }

    public function test_realtime_delivery_is_queued_so_an_unavailable_reverb_server_cannot_break_persistence(): void
    {
        config()->set('intsec.realtime_queue_connection', 'database');
        Queue::fake();

        $activity = RequestActivity::factory()->create(['source' => 'hotel-booking']);

        $this->assertModelExists($activity);
        Queue::assertPushed(BroadcastEvent::class);
    }

    public function test_broadcast_payload_recursively_redacts_secrets(): void
    {
        $event = new SecurityStateChanged('security_event', 'created', 'hotel-booking', [
            'id' => 10,
            'api_token' => 'do-not-broadcast',
            'nested' => ['Authorization' => 'Bearer secret', 'safe' => 'visible'],
        ]);

        $payload = $event->broadcastWith();

        $this->assertSame('[REDACTED]', $payload['record']['api_token']);
        $this->assertSame('[REDACTED]', $payload['record']['nested']['Authorization']);
        $this->assertSame('visible', $payload['record']['nested']['safe']);
    }

    public function test_private_security_channel_allows_administrators_and_rejects_standard_users(): void
    {
        $parameters = ['socket_id' => '1234.5678', 'channel_name' => 'private-intsec.security'];

        $this->actingAs(User::factory()->administrator()->create())
            ->post('/broadcasting/auth', $parameters)->assertOk();
        $this->actingAs(User::factory()->create())
            ->post('/broadcasting/auth', $parameters)->assertForbidden();
    }

    public function test_all_security_state_models_emit_realtime_changes_with_source_isolation(): void
    {
        Event::fake([SecurityStateChanged::class]);

        AuthenticationLog::factory()->create(['source' => 'hotel-booking']);
        SecurityEvent::record('Test event', 'test_event', 'High', source: 'hotel-booking');
        SecurityAlert::query()->create([
            'alert_id' => 'ALT-2026-900001', 'source' => 'hotel-booking', 'title' => 'Test alert',
            'alert_type' => 'test', 'severity' => 'High', 'status' => 'new', 'occurred_at' => now(),
        ]);
        Incident::query()->create([
            'title' => 'Test incident', 'source' => 'hotel-booking', 'incident_type' => 'test',
            'severity' => 'High', 'status' => 'open', 'event_count' => 1,
            'first_detected_at' => now(), 'last_detected_at' => now(),
        ]);
        $blocked = BlockedIp::query()->create([
            'ip_address' => '203.0.113.201', 'action' => 'block', 'source' => 'manual',
            'status' => 'active', 'is_enabled' => true, 'blocked_at' => now(),
        ]);
        $blocked->update(['is_enabled' => false]);

        foreach (['authentication_log', 'security_event', 'security_alert', 'incident', 'blocked_ip'] as $entity) {
            Event::assertDispatched(SecurityStateChanged::class, fn (SecurityStateChanged $event): bool => $event->entity === $entity
                && ($entity === 'blocked_ip' ? $event->source === null : $event->source === 'hotel-booking'));
        }
        Event::assertDispatched(SecurityStateChanged::class, fn (SecurityStateChanged $event): bool => $event->entity === 'blocked_ip' && $event->action === 'unblocked' && $event->record['is_enabled'] === false);
    }

    public function test_anonymous_clients_cannot_authorize_the_private_security_channel(): void
    {
        $this->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-intsec.security'])
            ->assertUnauthorized();
    }
}
