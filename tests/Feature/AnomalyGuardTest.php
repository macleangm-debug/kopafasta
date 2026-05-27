<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AnomalyGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AnomalyGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_ip_is_blocked_after_threshold_failed_logins(): void
    {
        // Seed (THRESHOLD - 1) historical failed logins from this IP
        for ($i = 0; $i < AnomalyGuard::IP_BLOCK_THRESHOLD - 1; $i++) {
            AuditLog::create([
                'event' => 'auth.failed_login',
                'ip_address' => '127.0.0.1',
                'new_values' => null,
            ]);
        }

        User::factory()->create([
            'email' => 'target@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
        ]);

        // One more failed login (different email each time to avoid per-key throttle)
        $this->postJson('/api/auth/login', [
            'email' => 'unknown'.uniqid().'@example.com',
            'password' => 'wrong',
        ])->assertStatus(422);

        $blocked = AuditLog::where('event', 'auth.ip_blocked')->first();
        $this->assertNotNull($blocked);
        $payload = json_decode($blocked->new_values, true);
        $this->assertSame('127.0.0.1', $payload['ip_address']);

        // Subsequent login attempt from same IP returns 429 even with valid creds
        $response = $this->postJson('/api/auth/login', [
            'email' => 'target@example.com',
            'password' => 'correct-password',
        ])->assertStatus(429)->assertJsonStructure(['message', 'retry_after']);

        $this->assertGreaterThan(0, $response->json('retry_after'));
        $this->assertTrue(AuditLog::where('event', 'auth.ip_block_hit')->exists());
    }

    public function test_user_is_auto_locked_after_threshold_failed_logins(): void
    {
        $user = User::factory()->create([
            'email' => 'victim@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
        ]);

        // Seed (THRESHOLD - 1) prior failed logins for this user
        for ($i = 0; $i < AnomalyGuard::USER_LOCK_THRESHOLD - 1; $i++) {
            AuditLog::create([
                'user_id' => $user->id,
                'event' => 'auth.failed_login',
                'ip_address' => '203.0.113.50',
                'new_values' => null,
            ]);
        }

        // Clear per-email throttle so we reach the controller
        RateLimiter::clear('login:victim@example.com|127.0.0.1');

        $this->postJson('/api/auth/login', [
            'email' => 'victim@example.com',
            'password' => 'wrong',
        ])->assertStatus(422);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->locked_until);
        $this->assertTrue($fresh->locked_until->isFuture());
        $this->assertTrue(AuditLog::where('event', 'auth.auto_locked')->where('user_id', $user->id)->exists());

        // Next login with correct password is rejected with 423
        $this->postJson('/api/auth/login', [
            'email' => 'victim@example.com',
            'password' => 'correct-password',
        ])->assertStatus(423);
    }

    public function test_ip_block_does_not_trigger_below_threshold(): void
    {
        for ($i = 0; $i < AnomalyGuard::IP_BLOCK_THRESHOLD - 2; $i++) {
            AuditLog::create([
                'event' => 'auth.failed_login',
                'ip_address' => '127.0.0.1',
                'new_values' => null,
            ]);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ])->assertStatus(422);

        $this->assertFalse(AuditLog::where('event', 'auth.ip_blocked')->exists());
    }
}
