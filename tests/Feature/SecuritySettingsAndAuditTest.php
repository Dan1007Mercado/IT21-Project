<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AuthenticationLog;
use App\Models\BlockedIp;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecuritySettingsAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_change_security_settings_and_persist_them(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->post('/admin/settings', [
                'max_login_attempts' => 7,
                'login_attempt_window_minutes' => 10,
                'login_block_duration_minutes' => 20,
                'failed_login_warning_threshold' => 3,
                'repeated_authentication_threshold' => 4,
                'repeated_ip_activity_threshold' => 6,
                'default_ip_block_duration_minutes' => 30,
            ])
            ->assertRedirect('/admin/settings');

        $this->assertDatabaseHas('system_settings', ['key' => 'max_login_attempts', 'value' => '7']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system_settings_changed',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_standard_user_cannot_access_admin_security_settings_or_audit_logs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/settings')->assertForbidden();
        $this->actingAs($user)->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_repeated_failed_login_attempts_trigger_ip_block_and_audit_record(): void
    {
        $admin = User::factory()->administrator()->create();
        $user = User::factory()->create([
            'email' => 'blocked-user@intsec.test',
            'password' => Hash::make('password'),
        ]);

        $this->app['config']->set('intsec.default_max_login_attempts', 2);
        $this->app['config']->set('intsec.default_login_attempt_window_minutes', 5);
        $this->app['config']->set('intsec.default_login_block_duration_minutes', 15);

        SystemSetting::query()->updateOrCreate(['key' => 'max_login_attempts'], ['value' => '2']);
        SystemSetting::query()->updateOrCreate(['key' => 'login_attempt_window_minutes'], ['value' => '5']);
        SystemSetting::query()->updateOrCreate(['key' => 'login_block_duration_minutes'], ['value' => '15']);

        $request = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

        $request->assertSessionHasErrors('email');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '203.0.113.25',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ip_blocked',
            'resource_type' => 'blocked_ip',
        ]);

        $this->assertDatabaseHas('authentication_logs', [
            'action' => 'login',
            'status' => 'failed',
            'ip_address' => '203.0.113.25',
        ]);
    }
}
