<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\Incident;
use App\Models\SecurityAlert;
use App\Models\User;
use App\Services\Security\IpManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    public function test_valid_ipv4_can_be_added(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '192.168.1.10',
            'action' => 'block',
            'reason' => 'Test block',
        ])->assertRedirect(route('ip-management.index'));

        $this->assertDatabaseHas('blocked_ips', ['ip_address' => '192.168.1.10', 'action' => 'block']);
    }

    public function test_valid_ipv6_can_be_added(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '2001:db8::1',
            'action' => 'allow',
            'reason' => 'Trusted v6 host',
        ])->assertRedirect();

        $this->assertDatabaseHas('blocked_ips', ['action' => 'allow']);
    }

    public function test_valid_cidr_can_be_added(): void
    {
        $admin = $this->admin();

        foreach (['192.168.1.0/24', '10.0.0.0/8', '2001:db8::/32'] as $cidr) {
            $this->actingAs($admin)->post('/ip-management', [
                'ip_address' => $cidr,
                'action' => 'block',
                'reason' => 'Range block '.$cidr,
            ])->assertRedirect();
        }

        $this->assertEquals(3, BlockedIp::query()->count());
    }

    public function test_cidr_is_normalized_to_its_network_address(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '192.168.1.42/24',
            'action' => 'block',
            'reason' => 'Equivalent range',
        ])->assertRedirect();

        $this->assertDatabaseHas('blocked_ips', ['ip_address' => '192.168.1.0/24']);

        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '192.168.1.0/24',
            'action' => 'block',
            'reason' => 'Duplicate normalized range',
        ])->assertSessionHasErrors('ip_address');
    }

    public function test_invalid_ip_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '999.999.999.999',
            'action' => 'block',
            'reason' => 'Bad',
        ])->assertSessionHasErrors('ip_address');

        $this->assertEquals(0, BlockedIp::query()->count());
    }

    public function test_invalid_cidr_is_rejected(): void
    {
        $admin = $this->admin();

        foreach (['192.168.1.0/33', '10.0.0.0/abc', '2001:db8::/129', 'not-an-ip/24'] as $bad) {
            $this->actingAs($admin)->post('/ip-management', [
                'ip_address' => $bad,
                'action' => 'block',
                'reason' => 'Bad',
            ])->assertSessionHasErrors('ip_address');
        }

        $this->assertEquals(0, BlockedIp::query()->count());
    }

    public function test_block_and_allow_rules_persist(): void
    {
        $service = app(IpManagementService::class);
        $admin = $this->admin();

        $block = $service->createRule(['ip_address' => '203.0.113.50', 'action' => 'block', 'reason' => 't'], $admin, '127.0.0.1');
        $allow = $service->createRule(['ip_address' => '198.51.100.7', 'action' => 'allow', 'reason' => 't'], $admin, '127.0.0.1');

        $this->assertEquals('block', $block->action);
        $this->assertEquals('allow', $allow->action);
        $this->assertDatabaseHas('blocked_ips', ['ip_address' => '203.0.113.50']);
        $this->assertDatabaseHas('blocked_ips', ['ip_address' => '198.51.100.7']);
    }

    public function test_disabled_rule_does_not_enforce(): void
    {
        $service = app(IpManagementService::class);
        $admin = $this->admin();

        $rule = $service->createRule(['ip_address' => '203.0.113.51', 'action' => 'block', 'reason' => 't'], $admin, '127.0.0.1');
        $this->assertTrue($service->isBlocked('203.0.113.51'));

        $service->setEnabled($rule, false, $admin, '127.0.0.1');
        $this->assertFalse($service->isBlocked('203.0.113.51'));
    }

    public function test_expired_block_does_not_enforce(): void
    {
        $service = app(IpManagementService::class);
        $admin = $this->admin();

        $service->createRule([
            'ip_address' => '203.0.113.52',
            'action' => 'block',
            'reason' => 'temporary',
            'expires_at' => now()->addMinutes(30),
        ], $admin, '127.0.0.1');

        $this->assertTrue($service->isBlocked('203.0.113.52'));

        BlockedIp::query()->where('ip_address', '203.0.113.52')->update(['expires_at' => now()->subMinute()]);

        // Fresh service state (decision queries DB each time).
        $this->assertFalse($service->isBlocked('203.0.113.52'));
        // Record remains for audit/history.
        $this->assertDatabaseHas('blocked_ips', ['ip_address' => '203.0.113.52']);
    }

    public function test_disabled_and_expired_allow_rules_are_neutral(): void
    {
        $service = app(IpManagementService::class);
        $admin = $this->admin();

        $disabled = $service->createRule([
            'ip_address' => '203.0.113.53',
            'action' => 'allow',
            'reason' => 'temporary exception',
            'is_enabled' => false,
        ], $admin, '127.0.0.1');
        $expired = $service->createRule([
            'ip_address' => '203.0.113.54',
            'action' => 'allow',
            'reason' => 'expired exception',
            'expires_at' => now()->subMinute(),
        ], $admin, '127.0.0.1');

        $this->assertSame(IpManagementService::DECISION_NONE, $service->decide($disabled->ip_address, false)['decision']);
        $this->assertSame(IpManagementService::DECISION_NONE, $service->decide($expired->ip_address, false)['decision']);
    }

    public function test_active_block_and_allow_detected(): void
    {
        $service = app(IpManagementService::class);
        $admin = $this->admin();

        $service->createRule(['ip_address' => '203.0.113.60', 'action' => 'block', 'reason' => 't'], $admin, '127.0.0.1');
        $service->createRule(['ip_address' => '198.51.100.60', 'action' => 'allow', 'reason' => 't'], $admin, '127.0.0.1');

        $this->assertTrue($service->isBlocked('203.0.113.60'));
        $this->assertTrue($service->isAllowed('198.51.100.60'));
        $this->assertFalse($service->isBlocked('198.51.100.60'));
    }

    public function test_block_takes_precedence_over_allow(): void
    {
        $service = app(IpManagementService::class);
        $admin = $this->admin();

        $service->createRule(['ip_address' => '10.0.0.0/8', 'action' => 'allow', 'reason' => 'trusted net'], $admin, '127.0.0.1');
        $service->createRule(['ip_address' => '10.1.2.3', 'action' => 'block', 'reason' => 'bad host'], $admin, '127.0.0.1');

        $this->assertTrue($service->isBlocked('10.1.2.3'));
        $this->assertTrue($service->isAllowed('10.9.9.9'));
    }

    public function test_duplicate_rules_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '203.0.113.70',
            'action' => 'block',
            'reason' => 'first',
        ])->assertRedirect();

        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '203.0.113.70',
            'action' => 'block',
            'reason' => 'second',
        ])->assertSessionHasErrors('ip_address');

        $this->assertEquals(1, BlockedIp::query()->where('ip_address', '203.0.113.70')->count());
    }

    public function test_alert_block_ip_works_and_is_idempotent(): void
    {
        $admin = $this->admin();
        $alert = SecurityAlert::query()->create([
            'alert_id' => 'ALT-2026-000001',
            'title' => 'Suspicious login',
            'severity' => 'High',
            'source_ip' => '203.0.113.80',
            'status' => 'new',
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)->post('/alerts/'.$alert->id.'/block-ip', [
            'reason' => 'Confirmed attacker',
        ])->assertRedirect();

        $this->assertTrue(app(IpManagementService::class)->isBlocked('203.0.113.80'));

        $rule = BlockedIp::query()->where('ip_address', '203.0.113.80')->first();
        $this->assertEquals('alert', $rule->source);
        $this->assertEquals($alert->id, $rule->alert_id);

        // Second call reuses the rule, no duplicate.
        $this->actingAs($admin)->post('/alerts/'.$alert->id.'/block-ip')->assertRedirect();
        $this->assertEquals(1, BlockedIp::query()->where('ip_address', '203.0.113.80')->count());
    }

    public function test_incident_block_ip_works_and_records_remark(): void
    {
        $admin = $this->admin();
        $incident = Incident::query()->create([
            'title' => 'Breach',
            'incident_type' => 'intrusion',
            'severity' => 'Critical',
            'status' => 'open',
            'source_ip' => '203.0.113.90',
            'event_count' => 1,
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        $this->actingAs($admin)->post('/incidents/'.$incident->id.'/block-ip', [
            'reason' => 'Containment',
        ])->assertRedirect();

        $this->assertTrue(app(IpManagementService::class)->isBlocked('203.0.113.90'));
        $this->assertStringContainsString(
            'blocked',
            strtolower($incident->remarks()->latest('id')->first()->remark)
        );
    }

    public function test_block_and_allow_actions_create_audit_logs(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '203.0.113.100',
            'action' => 'block',
            'reason' => 'audit check',
        ])->assertRedirect();

        $this->assertTrue(AuditLog::query()->where('action', 'ip_blocked')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'ip_rule_created')->exists());

        $rule = BlockedIp::query()->where('ip_address', '203.0.113.100')->first();
        $this->actingAs($admin)->delete('/ip-management/'.$rule->id)->assertRedirect();
        $this->assertTrue(AuditLog::query()->where('action', 'ip_rule_deleted')->exists());
    }

    public function test_search_filters_and_pagination_work(): void
    {
        $admin = $this->admin();
        $service = app(IpManagementService::class);

        $service->createRule(['ip_address' => '192.0.2.1', 'action' => 'block', 'reason' => 'needle-haystack'], $admin, '127.0.0.1');
        $service->createRule(['ip_address' => '192.0.2.2', 'action' => 'allow', 'reason' => 'other'], $admin, '127.0.0.1');

        $this->actingAs($admin)->get('/ip-management?search=needle-haystack')
            ->assertOk()->assertSee('192.0.2.1')->assertDontSee('192.0.2.2');

        $this->actingAs($admin)->get('/ip-management?action=allow')
            ->assertOk()->assertSee('192.0.2.2')->assertDontSee('192.0.2.1');

        $this->actingAs($admin)->get('/ip-management?source=manual')
            ->assertOk()->assertSee('192.0.2.1');
    }

    public function test_non_admin_cannot_manage_ips(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/ip-management')->assertForbidden();
        $this->actingAs($user)->post('/ip-management', [
            'ip_address' => '203.0.113.200',
            'action' => 'block',
            'reason' => 'x',
        ])->assertForbidden();
    }

    public function test_self_block_requires_confirmation(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '127.0.0.1',
            'action' => 'block',
            'reason' => 'oops',
        ]);

        $response->assertSessionHasErrors('ip_address');
        $this->assertEquals(0, BlockedIp::query()->count());
    }

    public function test_cidr_block_enforced_by_middleware_service(): void
    {
        $service = app(IpManagementService::class);
        $admin = $this->admin();

        $service->createRule(['ip_address' => '203.0.113.0/24', 'action' => 'block', 'reason' => 'range'], $admin, '127.0.0.1');

        $this->assertTrue($service->isBlocked('203.0.113.25'));
        $this->assertFalse($service->isBlocked('198.51.100.25'));
    }

    public function test_allow_cidr_is_enforced_and_outside_is_neutral(): void
    {
        $service = app(IpManagementService::class);
        $admin = $this->admin();

        $service->createRule(['ip_address' => '198.51.100.0/24', 'action' => 'allow', 'reason' => 'trusted range'], $admin, '127.0.0.1');

        $this->assertTrue($service->isAllowed('198.51.100.44'));
        $this->assertFalse($service->isBlocked('198.51.100.44'));

        $result = $service->decide('192.0.2.99', false);
        $this->assertEquals(IpManagementService::DECISION_NONE, $result['decision']);
        $this->assertNull($result['rule']);
    }

    public function test_broad_allow_cidr_with_narrow_block_cidr_overlap(): void
    {
        $service = app(IpManagementService::class);
        $admin = $this->admin();

        $service->createRule(['ip_address' => '192.168.1.0/24', 'action' => 'allow', 'reason' => 'office'], $admin, '127.0.0.1');
        $service->createRule(['ip_address' => '192.168.1.0/25', 'action' => 'block', 'reason' => 'compromised half'], $admin, '127.0.0.1');

        // 192.168.1.10 is inside 192.168.1.0/25 -> BLOCK wins.
        $this->assertTrue($service->isBlocked('192.168.1.10'));
        // 192.168.1.200 is outside /25 but inside /24 -> ALLOW.
        $this->assertTrue($service->isAllowed('192.168.1.200'));
        $this->assertFalse($service->isBlocked('192.168.1.200'));
    }

    public function test_ipv6_cidr_matching(): void
    {
        $service = app(IpManagementService::class);
        $admin = $this->admin();

        $service->createRule(['ip_address' => '2001:db8::/32', 'action' => 'block', 'reason' => 'v6 range'], $admin, '127.0.0.1');

        $this->assertTrue($service->isBlocked('2001:db8::abcd'));
        $this->assertFalse($service->isBlocked('2001:db9::1'));
    }

    public function test_match_count_increments_on_enforcement_only(): void
    {
        $service = app(IpManagementService::class);
        $admin = $this->admin();

        $rule = $service->createRule(['ip_address' => '203.0.113.210', 'action' => 'block', 'reason' => 'counter'], $admin, '127.0.0.1');
        $this->assertEquals(0, $rule->fresh()->match_count);

        // Investigation views must not inflate the counter.
        $service->decide('203.0.113.210', false);
        $this->assertEquals(0, $rule->fresh()->match_count);

        $service->decide('203.0.113.210');
        $service->decide('203.0.113.210');

        $fresh = $rule->fresh();
        $this->assertEquals(2, $fresh->match_count);
        $this->assertNotNull($fresh->last_matched_at);
    }

    public function test_self_block_with_confirmation_succeeds(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '127.0.0.1',
            'action' => 'block',
            'reason' => 'confirmed test',
            'confirm_self_block' => '1',
        ])->assertRedirect(route('ip-management.index'));

        $this->assertDatabaseHas('blocked_ips', ['ip_address' => '127.0.0.1', 'action' => 'block']);
    }

    public function test_cidr_self_block_requires_confirmation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '127.0.0.42/24',
            'action' => 'block',
            'reason' => 'too broad',
        ])->assertSessionHasErrors('ip_address');

        $this->assertDatabaseCount('blocked_ips', 0);

        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '127.0.0.42/24',
            'action' => 'block',
            'reason' => 'confirmed broad block',
            'confirm_self_block' => true,
        ])->assertRedirect(route('ip-management.index'));

        $this->assertDatabaseHas('blocked_ips', ['ip_address' => '127.0.0.0/24', 'action' => 'block']);
    }

    public function test_non_admin_cannot_modify_or_delete_rules(): void
    {
        $admin = $this->admin();
        $service = app(IpManagementService::class);
        $rule = $service->createRule(['ip_address' => '203.0.113.220', 'action' => 'block', 'reason' => 't'], $admin, '127.0.0.1');

        $user = User::factory()->create();

        $this->actingAs($user)->put('/ip-management/'.$rule->id, [
            'action' => 'allow', 'reason' => 'hijack',
        ])->assertForbidden();

        $this->actingAs($user)->patch('/ip-management/'.$rule->id.'/toggle', [
            'enabled' => false,
        ])->assertForbidden();

        $this->actingAs($user)->patch('/ip-management/'.$rule->id.'/action', [
            'action' => 'allow',
        ])->assertForbidden();

        $this->actingAs($user)->delete('/ip-management/'.$rule->id)->assertForbidden();

        // Nothing changed.
        $this->assertEquals('block', $rule->fresh()->action);
        $this->assertTrue($rule->fresh()->is_enabled);
        $this->assertDatabaseHas('blocked_ips', ['id' => $rule->id]);
    }

    public function test_enable_disable_and_action_switch_are_audited(): void
    {
        $admin = $this->admin();
        $service = app(IpManagementService::class);
        $rule = $service->createRule(['ip_address' => '203.0.113.230', 'action' => 'block', 'reason' => 't'], $admin, '127.0.0.1');

        $this->actingAs($admin)->patch('/ip-management/'.$rule->id.'/toggle', ['enabled' => false])->assertRedirect();
        $this->actingAs($admin)->patch('/ip-management/'.$rule->id.'/toggle', ['enabled' => true])->assertRedirect();
        $this->actingAs($admin)->patch('/ip-management/'.$rule->id.'/action', ['action' => 'allow'])->assertRedirect();
        $this->actingAs($admin)->put('/ip-management/'.$rule->id, [
            'action' => 'allow', 'reason' => 'reviewed', 'is_enabled' => true,
        ])->assertRedirect();

        $this->assertTrue(AuditLog::query()->where('action', 'ip_rule_disabled')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'ip_rule_enabled')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'ip_rule_updated')->exists());
        $this->assertEquals('allow', $rule->fresh()->action);
    }

    public function test_invalid_action_and_source_spoofing_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '203.0.113.240',
            'action' => 'destroy',
            'reason' => 'x',
        ])->assertSessionHasErrors('action');

        // source is server-assigned; forged values must not stick.
        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => '203.0.113.241',
            'action' => 'block',
            'reason' => 'x',
            'source' => 'system',
        ])->assertRedirect();

        $this->assertEquals('manual', BlockedIp::query()->where('ip_address', '203.0.113.241')->value('source'));
    }

    public function test_oversized_ip_value_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/ip-management', [
            'ip_address' => str_repeat('9', 65),
            'action' => 'block',
            'reason' => 'x',
        ])->assertSessionHasErrors('ip_address');

        $this->assertEquals(0, BlockedIp::query()->count());
    }

    public function test_alert_block_with_temporary_expiration(): void
    {
        $admin = $this->admin();
        $alert = SecurityAlert::query()->create([
            'alert_id' => 'ALT-2026-000099',
            'title' => 'Port scan',
            'severity' => 'High',
            'source_ip' => '203.0.113.250',
            'status' => 'new',
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)->post('/alerts/'.$alert->id.'/block-ip', [
            'reason' => 'Temporary containment',
            'expiration' => '30m',
        ])->assertRedirect();

        $rule = BlockedIp::query()->where('ip_address', '203.0.113.250')->first();
        $this->assertNotNull($rule);
        $this->assertNotNull($rule->expires_at);
        $this->assertTrue(app(IpManagementService::class)->isBlocked('203.0.113.250'));
        $this->assertTrue(AuditLog::query()->where('action', 'ip_auto_blocked')->exists());
    }

    public function test_blocked_ip_is_rejected_at_login(): void
    {
        $admin = $this->admin();
        app(IpManagementService::class)->createRule(
            ['ip_address' => '127.0.0.1', 'action' => 'block', 'reason' => 'enforcement check'],
            $admin,
            '127.0.0.1'
        );

        // Test client connects from 127.0.0.1 -> middleware must refuse.
        $response = $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'secret123']);
        $response->assertStatus(429);
    }

    public function test_blocked_login_is_counted_once_and_logged_without_ip_intelligence(): void
    {
        $admin = $this->admin();
        $rule = app(IpManagementService::class)->createRule(
            ['ip_address' => '127.0.0.1', 'action' => 'block', 'reason' => 'enforcement check'],
            $admin,
            '127.0.0.1'
        );

        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'secret123'])
            ->assertStatus(429);

        $this->assertSame(1, $rule->fresh()->match_count);
        $this->assertDatabaseHas('authentication_logs', [
            'ip_address' => '127.0.0.1',
            'attempted_identity' => 'nobody@example.test',
            'failure_reason' => 'ip_blocked',
        ]);
    }
}
