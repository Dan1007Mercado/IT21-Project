<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_search_filter_and_paginate_users_ten_at_a_time(): void
    {
        $admin = User::factory()->administrator()->create(['name' => 'Primary Admin']);
        User::factory()->count(11)->create();
        User::factory()->inactive()->create(['name' => 'Filtered Person', 'email' => 'filtered@example.test']);

        $response = $this->actingAs($admin)->get('/admin/users?search=Filtered&status=inactive');

        $response->assertOk()->assertSee('Filtered Person');
        $this->assertSame(['Filtered Person'], $response->viewData('users')->getCollection()->pluck('name')->all());
        $this->assertSame(10, $response->viewData('users')->perPage());
    }

    public function test_standard_user_cannot_access_user_administration(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->post('/admin/users', [])->assertForbidden();
        $this->actingAs($user)->put('/admin/users/'.$target->id, [])->assertForbidden();
        $this->actingAs($user)->delete('/admin/users/'.$target->id)->assertForbidden();
    }

    public function test_administrator_can_create_and_update_a_user_with_a_hashed_password_and_audit_records(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Hotel Analyst', 'email' => 'analyst@example.test', 'role' => 'standard_user',
            'is_active' => true, 'password' => 'Secure-password-123!', 'password_confirmation' => 'Secure-password-123!',
        ])->assertRedirect();

        $managed = User::query()->where('email', 'analyst@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Secure-password-123!', $managed->password));
        $originalPassword = $managed->password;
        $this->actingAs($admin)->put('/admin/users/'.$managed->id, [
            'name' => 'Hotel Security Analyst', 'email' => $managed->email, 'role' => 'administrator',
            'is_active' => true, 'password' => '', 'password_confirmation' => '',
        ])->assertRedirect(route('users.show', $managed));

        $this->assertDatabaseHas('users', ['id' => $managed->id, 'name' => 'Hotel Security Analyst', 'role' => 'administrator']);
        $this->assertSame($originalPassword, $managed->fresh()->password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_created', 'resource_id' => $managed->id, 'actor_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_updated', 'resource_id' => $managed->id, 'actor_id' => $admin->id]);
        $this->assertStringNotContainsString('Secure-password-123!', json_encode(AuditLog::query()->get()->toArray()));
    }

    public function test_duplicate_email_is_rejected_and_an_explicit_password_change_is_hashed(): void
    {
        $admin = User::factory()->administrator()->create();
        $managed = User::factory()->create(['email' => 'existing@example.test']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Duplicate', 'email' => 'existing@example.test', 'role' => 'standard_user',
            'is_active' => true, 'password' => 'Secure-password-123!', 'password_confirmation' => 'Secure-password-123!',
        ])->assertSessionHasErrors('email');

        $this->actingAs($admin)->put('/admin/users/'.$managed->id, [
            'name' => $managed->name, 'email' => $managed->email, 'role' => 'standard_user',
            'is_active' => true, 'password' => 'New-secure-password-456!', 'password_confirmation' => 'New-secure-password-456!',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('New-secure-password-456!', $managed->fresh()->password));
    }

    public function test_administrator_can_delete_another_user_but_not_self(): void
    {
        $admin = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)->delete('/admin/users/'.$target->id)->assertRedirect(route('users.index'));
        $this->assertModelMissing($target);
        $this->actingAs($admin)->delete('/admin/users/'.$admin->id)->assertSessionHasErrors('user');
        $this->assertModelExists($admin);
    }

    public function test_last_active_administrator_and_current_session_cannot_be_locked_out(): void
    {
        $onlyActive = User::factory()->administrator()->create();
        $inactiveAdmin = User::factory()->administrator()->inactive()->create();

        $this->actingAs($inactiveAdmin)->delete('/admin/users/'.$onlyActive->id)->assertSessionHasErrors('user');
        $this->actingAs($onlyActive)->put('/admin/users/'.$onlyActive->id, [
            'name' => $onlyActive->name, 'email' => $onlyActive->email, 'role' => 'standard_user',
            'is_active' => true, 'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', ['id' => $onlyActive->id, 'role' => 'administrator', 'is_active' => true]);
    }
}
