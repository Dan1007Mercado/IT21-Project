<?php

namespace Tests\Feature;

use App\Models\SecurityAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_view_alert_and_convert_to_incident(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->post('/alerts', [
                'title' => 'Test alert',
                'severity' => 'High',
            ])
            ->assertRedirect();

        $alert = SecurityAlert::query()->first();
        $this->assertNotNull($alert);

        $this->actingAs($admin)->get('/alerts')->assertOk()->assertSee('Test alert');

        $this->actingAs($admin)->post('/alerts/'.$alert->id.'/create-incident')->assertRedirect();

        $alert->refresh();
        $this->assertNotNull($alert->incident_id);
    }

    public function test_non_admin_cannot_access_alert_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/alerts')->assertForbidden();
    }
}
