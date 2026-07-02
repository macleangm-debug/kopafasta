<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_enable_returns_secret_and_recovery_codes(): void
    {
        $user = User::factory()->create(['role' => 'officer']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/2fa/enable')
            ->assertOk()
            ->assertJsonStructure(['secret', 'provisioning_uri', 'recovery_codes']);

        $this->assertCount(8, $response->json('recovery_codes'));
        $this->assertNotEmpty($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertTrue(AuditLog::where('event', 'auth.2fa_enabled')->exists());
    }

    public function test_confirm_with_valid_code_activates_2fa(): void
    {
        $user = User::factory()->create(['role' => 'officer']);
        Sanctum::actingAs($user);

        $secret = $this->postJson('/api/auth/2fa/enable')->json('secret');
        $code = app(TotpService::class)->currentCode($secret);

        $this->postJson('/api/auth/2fa/confirm', ['code' => $code])->assertOk();

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertTrue(AuditLog::where('event', 'auth.2fa_confirmed')->exists());
    }

    public function test_confirm_with_invalid_code_fails(): void
    {
        $user = User::factory()->create(['role' => 'officer']);
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/2fa/enable');
        $this->postJson('/api/auth/2fa/confirm', ['code' => '000000'])->assertStatus(422);

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_login_requires_2fa_code_when_enabled(): void
    {
        Config::set('auth_portal.require_2fa_admin', true);

        $secret = app(TotpService::class)->generateSecret();
        $user = User::factory()->create([
            'email' => 'tfa@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['recoveryone', 'recoverytwo'],
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'tfa@example.com',
            'password' => 'correct-password',
        ])->assertStatus(401)->assertJsonPath('requires_two_factor', true);
    }

    public function test_login_succeeds_with_valid_2fa_code(): void
    {
        Config::set('auth_portal.require_2fa_admin', true);

        $secret = app(TotpService::class)->generateSecret();
        $code = app(TotpService::class)->currentCode($secret);

        User::factory()->create([
            'email' => 'tfa@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['recoveryone'],
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'tfa@example.com',
            'password' => 'correct-password',
            'two_factor_code' => $code,
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_login_accepts_recovery_code_and_consumes_it(): void
    {
        Config::set('auth_portal.require_2fa_admin', true);

        $secret = app(TotpService::class)->generateSecret();
        $user = User::factory()->create([
            'email' => 'tfa@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['recoveryone', 'recoverytwo'],
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'tfa@example.com',
            'password' => 'correct-password',
            'two_factor_code' => 'recoveryone',
        ])->assertOk();

        $remaining = $user->fresh()->two_factor_recovery_codes;
        $this->assertSame(['recoverytwo'], $remaining);
        $this->assertTrue(AuditLog::where('event', 'auth.2fa_recovery_used')->exists());
    }

    public function test_login_with_wrong_2fa_code_fails_and_audits(): void
    {
        Config::set('auth_portal.require_2fa_admin', true);

        $secret = app(TotpService::class)->generateSecret();
        User::factory()->create([
            'email' => 'tfa@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'tfa@example.com',
            'password' => 'correct-password',
            'two_factor_code' => '000000',
        ])->assertStatus(422);

        $this->assertTrue(AuditLog::where('event', 'auth.2fa_failed')->exists());
    }

    public function test_disable_requires_password_and_code(): void
    {
        $secret = app(TotpService::class)->generateSecret();
        $code = app(TotpService::class)->currentCode($secret);

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/2fa/disable', [
            'password' => 'wrong',
            'code' => $code,
        ])->assertStatus(422);

        $this->postJson('/api/auth/2fa/disable', [
            'password' => 'correct-password',
            'code' => $code,
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_confirmed_at);
        $this->assertNull($fresh->two_factor_recovery_codes);
        $this->assertTrue(AuditLog::where('event', 'auth.2fa_disabled')->exists());
    }
}
