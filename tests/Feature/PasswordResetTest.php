<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_generic_message_for_unknown_email(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If the email exists, a reset link was issued.');

        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertTrue(AuditLog::where('event', 'auth.password_reset_requested_unknown')->exists());
    }

    public function test_forgot_password_creates_hashed_token_for_known_user(): void
    {
        User::factory()->create(['email' => 'reset@example.com', 'role' => 'officer']);

        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com'])->assertOk();
        $plain = $response->json('reset_token');
        $this->assertNotEmpty($plain);

        $row = DB::table('password_reset_tokens')->where('email', 'reset@example.com')->first();
        $this->assertNotNull($row);
        $this->assertNotSame($plain, $row->token, 'token must be hashed at rest');
        $this->assertTrue(Hash::check($plain, $row->token));

        $this->assertTrue(AuditLog::where('event', 'auth.password_reset_requested')->exists());
    }

    public function test_reset_password_with_invalid_token_fails(): void
    {
        User::factory()->create(['email' => 'reset@example.com', 'role' => 'officer']);
        $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com']);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => 'wrong-token',
            'password' => 'NewStrongPass1',
            'password_confirmation' => 'NewStrongPass1',
        ])->assertStatus(422);

        $this->assertTrue(AuditLog::where('event', 'auth.password_reset_invalid')->exists());
    }

    public function test_reset_password_with_expired_token_fails(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com', 'role' => 'officer']);
        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com']);
        $plain = $response->json('reset_token');

        DB::table('password_reset_tokens')->where('email', $user->email)
            ->update(['created_at' => now()->subHours(3)]);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $plain,
            'password' => 'NewStrongPass1',
            'password_confirmation' => 'NewStrongPass1',
        ])->assertStatus(422);

        $this->assertTrue(AuditLog::where('event', 'auth.password_reset_expired')->exists());
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_reset_password_succeeds_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'role' => 'officer',
            'password' => bcrypt('OldPass12345'),
            'locked_until' => now()->addHour(),
        ]);
        $user->createToken('existing-1');
        $user->createToken('existing-2');

        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com']);
        $plain = $response->json('reset_token');

        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $plain,
            'password' => 'NewStrongPass1',
            'password_confirmation' => 'NewStrongPass1',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('NewStrongPass1', $fresh->password));
        $this->assertNull($fresh->locked_until);
        $this->assertSame(0, $fresh->tokens()->count());
        $this->assertDatabaseCount('password_reset_tokens', 0);

        $this->assertTrue(AuditLog::where('event', 'auth.password_reset')->where('user_id', $user->id)->exists());

        // Old password no longer works; new one does
        $this->postJson('/api/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'OldPass12345',
        ])->assertStatus(422);

        $this->postJson('/api/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'NewStrongPass1',
        ])->assertOk();
    }

    public function test_reset_password_rejects_weak_new_password(): void
    {
        User::factory()->create(['email' => 'reset@example.com', 'role' => 'officer']);
        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com']);
        $plain = $response->json('reset_token');

        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $plain,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }
}
