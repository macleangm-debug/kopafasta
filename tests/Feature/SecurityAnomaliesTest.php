<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityAnomaliesTest extends TestCase
{
    use RefreshDatabase;

    private function seedAnomalies(): array
    {
        $alice = User::factory()->create(['email' => 'alice@example.com', 'role' => 'officer']);
        $bob = User::factory()->create(['email' => 'bob@example.com', 'role' => 'officer']);

        // Alice: 4 failed logins from 198.51.100.1, 1 new_device
        foreach (range(1, 4) as $i) {
            AuditLog::create([
                'user_id' => $alice->id, 'event' => 'auth.failed_login',
                'ip_address' => '198.51.100.1',
            ]);
        }
        AuditLog::create([
            'user_id' => $alice->id, 'event' => 'auth.new_device_login',
            'ip_address' => '198.51.100.1',
        ]);

        // Bob: 2 failed logins from 203.0.113.5
        foreach (range(1, 2) as $i) {
            AuditLog::create([
                'user_id' => $bob->id, 'event' => 'auth.failed_login',
                'ip_address' => '203.0.113.5',
            ]);
        }

        // Old failed login (35 days ago) should be excluded
        AuditLog::create([
            'user_id' => $alice->id, 'event' => 'auth.failed_login',
            'ip_address' => '198.51.100.1',
            'created_at' => now()->subDays(35), 'updated_at' => now()->subDays(35),
        ]);

        return [$alice, $bob];
    }

    public function test_admin_can_view_anomalies_summary(): void
    {
        [$alice, $bob] = $this->seedAnomalies();
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/system/security/anomalies?days=7')->assertOk()
            ->assertJsonStructure([
                'window_days', 'since', 'totals', 'top_users', 'top_failed_ips',
            ]);

        $body = $response->json();
        $this->assertSame(7, $body['window_days']);
        $this->assertSame(6, $body['totals']['auth.failed_login']);
        $this->assertSame(1, $body['totals']['auth.new_device_login']);

        // top_users sorted by total desc → alice (5) first, bob (2) second
        $this->assertSame($alice->id, $body['top_users'][0]['user_id']);
        $this->assertSame(5, $body['top_users'][0]['total']);
        $this->assertSame($bob->id, $body['top_users'][1]['user_id']);

        // top_failed_ips
        $this->assertSame('198.51.100.1', $body['top_failed_ips'][0]['ip_address']);
        $this->assertSame(4, $body['top_failed_ips'][0]['failed_count']);
    }

    public function test_manager_cannot_view_anomalies(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($manager);

        $this->getJson('/api/system/security/anomalies')->assertStatus(403);
    }

    public function test_unauthenticated_request_denied(): void
    {
        $this->getJson('/api/system/security/anomalies')->assertStatus(401);
    }

    public function test_days_parameter_is_validated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/system/security/anomalies?days=999')->assertStatus(422);
    }
}
