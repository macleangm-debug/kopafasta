<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrustedDevicesTest extends TestCase
{
    use RefreshDatabase;

    private function user2fa(): array
    {
        $secret = app(TotpService::class)->generateSecret();
        $user = User::factory()->create([
            'email' => 'td@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        return [$user, $secret];
    }

    public function test_login_with_trust_device_returns_token_and_creates_row(): void
    {
        [$user, $secret] = $this->user2fa();
        $code = app(TotpService::class)->currentCode($secret);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'two_factor_code' => $code,
            'trust_device' => true,
        ])->assertOk()->assertJsonStructure(['trusted_device_token']);

        $this->assertNotEmpty($response->json('trusted_device_token'));
        $this->assertDatabaseCount('trusted_devices', 1);
        $this->assertTrue(AuditLog::where('event', 'auth.device_trusted')->exists());
    }

    public function test_trusted_device_token_allows_login_without_2fa_code(): void
    {
        [$user, $secret] = $this->user2fa();
        $code = app(TotpService::class)->currentCode($secret);

        $trustToken = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'two_factor_code' => $code,
            'trust_device' => true,
        ])->json('trusted_device_token');

        $this->withHeader('X-Trusted-Device', $trustToken)
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'correct-password',
            ])
            ->assertOk()
            ->assertJsonStructure(['token']);

        $this->assertTrue(AuditLog::where('event', 'auth.device_trust_used')->exists());
        $this->assertNotNull(TrustedDevice::first()->fresh()->last_used_at);
    }

    public function test_expired_trusted_device_token_falls_back_to_2fa(): void
    {
        [$user] = $this->user2fa();
        $plain = 'plain-trust-token-1234567890';
        TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'name' => 'Old laptop',
            'expires_at' => now()->subDay(),
        ]);

        $this->withHeader('X-Trusted-Device', $plain)
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'correct-password',
            ])->assertStatus(401)->assertJsonPath('requires_two_factor', true);
    }

    public function test_invalid_trusted_device_token_falls_back_to_2fa(): void
    {
        [$user] = $this->user2fa();

        $this->withHeader('X-Trusted-Device', 'totally-bogus-token')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'correct-password',
            ])->assertStatus(401)->assertJsonPath('requires_two_factor', true);
    }

    public function test_user_can_list_and_revoke_their_trusted_devices(): void
    {
        $user = User::factory()->create(['role' => 'officer']);
        $device = TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'a'),
            'name' => 'Phone',
            'expires_at' => now()->addDays(30),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/trusted-devices')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'expires_at', 'expired']]])
            ->assertJsonPath('data.0.id', $device->id);

        $this->deleteJson('/api/auth/trusted-devices/'.$device->id)->assertOk();
        $this->assertDatabaseMissing('trusted_devices', ['id' => $device->id]);
        $this->assertTrue(AuditLog::where('event', 'auth.device_trust_revoked')->exists());
    }

    public function test_user_cannot_revoke_another_users_trusted_device(): void
    {
        $alice = User::factory()->create(['role' => 'officer']);
        $bob = User::factory()->create(['role' => 'officer']);
        $bobDevice = TrustedDevice::create([
            'user_id' => $bob->id,
            'token_hash' => hash('sha256', 'b'),
            'name' => 'Bob phone',
            'expires_at' => now()->addDays(30),
        ]);

        Sanctum::actingAs($alice);

        $this->deleteJson('/api/auth/trusted-devices/'.$bobDevice->id)->assertStatus(404);
        $this->assertDatabaseHas('trusted_devices', ['id' => $bobDevice->id]);
    }

    public function test_trust_device_flag_does_nothing_when_2fa_not_enabled(): void
    {
        $user = User::factory()->create([
            'email' => 'no2fa@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'trust_device' => true,
        ])->assertOk();

        $this->assertNull($response->json('trusted_device_token'));
        $this->assertDatabaseCount('trusted_devices', 0);
    }
}
