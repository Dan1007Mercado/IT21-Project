<?php

namespace Tests\Feature;

use App\Models\AuthenticationLog;
use App\Models\RequestActivity;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Security\AuthenticationDetectionService;
use App\Services\Security\ClientIpResolver;
use App\Services\Security\IpClassifier;
use App\Services\Security\IpEnrichmentService;
use App\Services\Security\RequestDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UpgradedMonitoringArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_activity_is_captured_without_sensitive_body_data(): void
    {
        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'super-secret']);

        $activity = RequestActivity::query()->where('path', '/login')->firstOrFail();
        $this->assertSame('POST', $activity->method);
        $this->assertSame(302, $activity->status_code);
        $this->assertArrayNotHasKey('password', $activity->metadata ?? []);
        $this->assertStringNotContainsString('super-secret', json_encode($activity->toArray()));
    }

    public function test_health_and_static_paths_are_excluded_from_request_telemetry(): void
    {
        Http::fake();
        Queue::fake();

        $this->get('/up')->assertOk();
        $this->get('/favicon.ico');

        $this->assertDatabaseCount('request_activities', 0);
        $this->assertDatabaseCount('security_events', 0);
        $this->assertDatabaseCount('security_alerts', 0);
        $this->assertDatabaseCount('ip_intelligences', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_ip_classifier_distinguishes_network_types(): void
    {
        $classifier = app(IpClassifier::class);
        $this->assertSame('loopback', $classifier->classify('127.0.0.1'));
        $this->assertSame('loopback', $classifier->classify('::1'));
        $this->assertSame('private', $classifier->classify('192.168.1.10'));
        $this->assertSame('private', $classifier->classify('172.16.8.4'));
        $this->assertSame('public', $classifier->classify('8.8.8.8'));
        $this->assertSame('invalid', $classifier->classify('not-an-ip'));
    }

    public function test_forwarded_ip_is_used_only_for_a_trusted_proxy(): void
    {
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '10.0.0.10']);
        $request->headers->set('X-Forwarded-For', '8.8.8.8');
        $resolver = app(ClientIpResolver::class);

        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        $this->assertSame('10.0.0.10', $resolver->resolve($request)['ip']);

        Request::setTrustedProxies(['10.0.0.10'], Request::HEADER_X_FORWARDED_FOR);
        $this->assertSame('8.8.8.8', $resolver->resolve($request)['ip']);
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
    }

    public function test_cloudflare_connecting_ip_is_used_for_local_peer(): void
    {
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $request->headers->set('CF-Connecting-IP', '8.8.4.4');
        $resolver = app(ClientIpResolver::class);

        $this->assertSame('8.8.4.4', $resolver->resolve($request)['ip']);

        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '203.0.113.10']);
        $request->headers->set('CF-Connecting-IP', '8.8.4.4');

        $this->assertSame('203.0.113.10', $resolver->resolve($request)['ip']);
    }

    public function test_private_ip_is_persisted_but_never_sent_to_enrichment_provider(): void
    {
        Http::fake();
        app(IpEnrichmentService::class)->observe('192.168.10.2');
        $this->assertDatabaseHas('ip_intelligences', ['ip_address' => '192.168.10.2', 'ip_type' => 'private']);
        Http::assertNothingSent();
    }

    public function test_repeated_404_and_sensitive_paths_create_explainable_deduplicated_alerts(): void
    {
        SystemSetting::query()->create(['key' => 'repeated_404_threshold', 'value' => '2']);
        SystemSetting::query()->create(['key' => 'sensitive_path_probe_threshold', 'value' => '2']);
        $service = app(RequestDetectionService::class);

        foreach (['/.env', '/.git/config'] as $path) {
            $activity = RequestActivity::factory()->create(['ip_address' => '8.8.8.8', 'path' => $path, 'status_code' => 404]);
            $service->evaluate($activity);
        }

        $this->assertDatabaseHas('security_alerts', ['rule_key' => 'request.repeated_404', 'occurrence_count' => 1]);
        $this->assertDatabaseHas('security_alerts', ['rule_key' => 'request.sensitive_path_probe', 'occurrence_count' => 1]);
        $this->assertSame(2, SecurityAlert::query()->where('rule_key', 'request.sensitive_path_probe')->firstOrFail()->metadata['observed_count']);

        $third = RequestActivity::factory()->create(['ip_address' => '8.8.8.8', 'path' => '/.env', 'status_code' => 404]);
        $service->evaluate($third);
        $this->assertSame(2, SecurityAlert::query()->where('rule_key', 'request.sensitive_path_probe')->firstOrFail()->occurrence_count);
    }

    public function test_decoy_endpoint_access_creates_an_immediate_high_alert(): void
    {
        $activity = RequestActivity::factory()->create(['ip_address' => '8.8.8.8', 'path' => '/decoy/login', 'status_code' => 404]);
        app(RequestDetectionService::class)->evaluate($activity);

        $this->assertDatabaseHas('security_alerts', ['rule_key' => 'request.decoy_access', 'severity' => 'High']);
    }

    public function test_authentication_rules_distinguish_brute_force_spray_and_distributed_attack(): void
    {
        foreach (['brute_force_threshold', 'password_spray_threshold', 'distributed_attack_threshold'] as $key) {
            SystemSetting::query()->create(['key' => $key, 'value' => '2']);
        }
        SystemSetting::query()->create(['key' => 'repeated_authentication_threshold', 'value' => '20']);
        $service = app(AuthenticationDetectionService::class);

        foreach ([
            ['victim@example.test', '8.8.8.8'],
            ['victim@example.test', '8.8.8.8'],
            ['other@example.test', '8.8.8.8'],
            ['victim@example.test', '1.1.1.1'],
        ] as [$identity, $ip]) {
            $service->evaluate(AuthenticationLog::factory()->create(['action' => 'login', 'status' => 'failed', 'attempted_identity' => $identity, 'ip_address' => $ip, 'occurred_at' => now()]));
        }

        $this->assertDatabaseHas('security_alerts', ['rule_key' => 'auth.account_brute_force']);
        $this->assertDatabaseHas('security_alerts', ['rule_key' => 'auth.password_spray']);
        $this->assertDatabaseHas('security_alerts', ['rule_key' => 'auth.distributed_account_attack']);
    }

    public function test_failures_followed_by_success_create_correlated_incident(): void
    {
        SystemSetting::query()->create(['key' => 'failed_login_warning_threshold', 'value' => '2']);
        $service = app(AuthenticationDetectionService::class);
        foreach (range(1, 2) as $_) {
            $service->evaluate(AuthenticationLog::factory()->create(['action' => 'login', 'status' => 'failed', 'attempted_identity' => 'victim@example.test', 'ip_address' => '8.8.4.4', 'occurred_at' => now()]));
        }
        $success = AuthenticationLog::factory()->create(['action' => 'login', 'status' => 'successful', 'attempted_identity' => 'victim@example.test', 'ip_address' => '8.8.4.4', 'occurred_at' => now()]);
        $service->evaluate($success);

        $this->assertDatabaseHas('security_alerts', ['rule_key' => 'auth.failed_then_success']);
        $this->assertDatabaseHas('incidents', ['source_ip' => '8.8.4.4', 'incident_type' => 'correlated_activity']);
    }

    public function test_successful_login_from_new_ip_and_user_agent_is_suspicious_after_known_history(): void
    {
        $user = User::factory()->create();
        AuthenticationLog::factory()->create([
            'user_id' => $user->id, 'action' => 'login', 'status' => 'successful',
            'attempted_identity' => $user->email, 'ip_address' => '8.8.8.8',
            'user_agent' => 'Known browser', 'occurred_at' => now()->subDay(),
        ]);
        $newContext = AuthenticationLog::factory()->create([
            'user_id' => $user->id, 'action' => 'login', 'status' => 'successful',
            'attempted_identity' => $user->email, 'ip_address' => '1.1.1.1',
            'user_agent' => 'New browser', 'occurred_at' => now(),
        ]);

        app(AuthenticationDetectionService::class)->evaluate($newContext);
        $this->assertDatabaseHas('security_alerts', ['rule_key' => 'auth.suspicious_success_context']);
    }

    public function test_dashboard_is_read_only_and_existing_session_does_not_log_a_new_login(): void
    {
        $admin = User::factory()->administrator()->create();
        RequestActivity::factory()->count(4)->create(['ip_address' => '8.8.8.8']);
        $events = SecurityEvent::query()->count();
        $logs = AuthenticationLog::query()->count();

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/attack-frequency')->assertOk();

        $this->assertSame($events, SecurityEvent::query()->count());
        $this->assertSame($logs, AuthenticationLog::query()->count());
    }
}
