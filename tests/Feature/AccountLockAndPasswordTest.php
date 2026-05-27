<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountLockAndPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_rejects_weak_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_register_accepts_strong_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $response->assertCreated()->assertJsonStructure(['user' => ['id', 'email'], 'token']);
    }

    public function test_locked_account_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'locked@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
            'locked_until' => now()->addHour(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'locked@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(423)->assertJsonStructure(['message', 'locked_until']);
        $this->assertTrue(AuditLog::where('event', 'auth.login_locked_account')->exists());
    }

    public function test_expired_lock_allows_login(): void
    {
        User::factory()->create([
            'email' => 'expired@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
            'locked_until' => now()->subMinute(),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'expired@example.com',
            'password' => 'correct-password',
        ])->assertOk();
    }

    public function test_admin_can_lock_and_unlock_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'officer']);

        Sanctum::actingAs($admin);

        $this->postJson("/api/system/users/{$target->id}/lock", ['minutes' => 30, 'reason' => 'investigation'])
            ->assertOk()
            ->assertJsonPath('id', $target->id);

        $this->assertNotNull($target->fresh()->locked_until);
        $this->assertTrue(AuditLog::where('event', 'admin.user_locked')->exists());

        $this->postJson("/api/system/users/{$target->id}/unlock")->assertOk();
        $this->assertNull($target->fresh()->locked_until);
        $this->assertTrue(AuditLog::where('event', 'admin.user_unlocked')->exists());
    }

    public function test_manager_cannot_lock_user(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $target = User::factory()->create(['role' => 'officer']);

        Sanctum::actingAs($manager);

        $this->postJson("/api/system/users/{$target->id}/lock")->assertStatus(403);
    }

    public function test_change_password_requires_current_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('OldPass12345'),
            'role' => 'officer',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'WrongPass',
            'password' => 'NewStrongPass1',
            'password_confirmation' => 'NewStrongPass1',
        ])->assertStatus(422);
    }

    public function test_change_password_rejects_weak_new_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('OldPass12345'),
            'role' => 'officer',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'OldPass12345',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_change_password_succeeds_and_logs_audit(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('OldPass12345'),
            'role' => 'officer',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'OldPass12345',
            'password' => 'NewStrongPass1',
            'password_confirmation' => 'NewStrongPass1',
        ])->assertOk();

        $this->assertTrue(AuditLog::where('event', 'auth.password_changed')->where('user_id', $user->id)->exists());

        // Old password no longer works
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'OldPass12345',
        ])->assertStatus(422);
    }
}
