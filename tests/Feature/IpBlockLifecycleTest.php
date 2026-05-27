<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IpBlockLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_expire_blocks_command_clears_cache_and_emits_event(): void
    {
        $ip = '203.0.113.42';
        $blockedAt = now()->subHours(2);
        $blockedUntil = now()->subHour(); // already past

        Cache::put('sec:ip_blocked:'.$ip, 1, now()->addHour());
        Cache::put('sec:ip_blocked:'.$ip.':expires', $blockedUntil->timestamp, now()->addHour());

        AuditLog::create([
            'event' => 'auth.ip_blocked',
            'ip_address' => $ip,
            'new_values' => json_encode([
                'ip_address' => $ip,
                'failed_count' => 25,
                'blocked_until' => $blockedUntil->toIso8601String(),
            ]),
            'created_at' => $blockedAt,
            'updated_at' => $blockedAt,
        ]);

        $this->artisan('security:expire-blocks')->assertSuccessful();

        $this->assertFalse(Cache::has('sec:ip_blocked:'.$ip));
        $this->assertTrue(AuditLog::where('event', 'auth.ip_block_expired')->where('ip_address', $ip)->exists());

        // Running again should NOT emit a second expiry for the same block.
        $this->artisan('security:expire-blocks')->assertSuccessful();
        $this->assertSame(1, AuditLog::where('event', 'auth.ip_block_expired')->where('ip_address', $ip)->count());
    }

    public function test_expire_blocks_skips_still_active_block(): void
    {
        $ip = '203.0.113.99';
        $future = now()->addMinutes(30);

        Cache::put('sec:ip_blocked:'.$ip, 1, $future);
        Cache::put('sec:ip_blocked:'.$ip.':expires', $future->timestamp, $future);

        AuditLog::create([
            'event' => 'auth.ip_blocked',
            'ip_address' => $ip,
            'new_values' => json_encode([
                'ip_address' => $ip,
                'blocked_until' => $future->toIso8601String(),
            ]),
        ]);

        $this->artisan('security:expire-blocks')->assertSuccessful();

        $this->assertTrue(Cache::has('sec:ip_blocked:'.$ip));
        $this->assertFalse(AuditLog::where('event', 'auth.ip_block_expired')->exists());
    }

    public function test_admin_can_unblock_an_ip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $ip = '198.51.100.7';
        Cache::put('sec:ip_blocked:'.$ip, 1, now()->addHour());
        Cache::put('sec:ip_blocked:'.$ip.':expires', now()->addHour()->timestamp, now()->addHour());

        $this->deleteJson("/api/system/security/blocks/{$ip}")
            ->assertOk()
            ->assertJsonFragment(['ip_address' => $ip, 'was_blocked' => true]);

        $this->assertFalse(Cache::has('sec:ip_blocked:'.$ip));
        $this->assertTrue(AuditLog::where('event', 'admin.ip_unblocked')->exists());
    }

    public function test_admin_unblock_rejects_invalid_ip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->deleteJson('/api/system/security/blocks/999.999.999.999')->assertStatus(422);
    }

    public function test_non_admin_cannot_unblock_ip(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($manager);

        $this->deleteJson('/api/system/security/blocks/198.51.100.7')->assertStatus(403);
    }
}
