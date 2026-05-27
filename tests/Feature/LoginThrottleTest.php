<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:throttle@example.com|127.0.0.1');
    }

    public function test_failed_login_writes_audit_and_increments_attempts(): void
    {
        User::factory()->create([
            'email' => 'throttle@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'throttle@example.com',
            'password' => 'wrong',
        ])->assertStatus(422);

        $log = AuditLog::where('event', 'auth.failed_login')->latest()->first();
        $this->assertNotNull($log);
        $payload = json_decode($log->new_values, true);
        $this->assertSame('throttle@example.com', $payload['email']);
        $this->assertSame(4, $payload['remaining_attempts']);
    }

    public function test_login_locks_after_max_attempts(): void
    {
        User::factory()->create([
            'email' => 'throttle@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'throttle@example.com',
                'password' => 'wrong',
            ])->assertStatus(422);
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => 'throttle@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(429)->assertJsonStructure(['message', 'retry_after']);

        $this->assertTrue(AuditLog::where('event', 'auth.login_locked')->exists());
    }

    public function test_successful_login_clears_attempts_and_logs_success(): void
    {
        User::factory()->create([
            'email' => 'throttle@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'throttle@example.com',
            'password' => 'wrong',
        ])->assertStatus(422);

        $this->postJson('/api/auth/login', [
            'email' => 'throttle@example.com',
            'password' => 'correct-password',
        ])->assertOk();

        $this->assertTrue(AuditLog::where('event', 'auth.login_success')->exists());

        // After a success the counter is cleared so 5 more wrong attempts should not lock immediately.
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'throttle@example.com',
                'password' => 'wrong',
            ])->assertStatus(422);
        }
    }

    public function test_inactive_account_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'correct-password',
        ])->assertStatus(403);

        $this->assertTrue(AuditLog::where('event', 'auth.login_inactive')->exists());
    }
}
