<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\SecurityIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityIntelTest extends TestCase
{
    use RefreshDatabase;

    public function test_classify_marks_loopback_and_private_addresses(): void
    {
        $svc = new SecurityIntel();

        $this->assertTrue($svc->classify('127.0.0.1')['private']);
        $this->assertTrue($svc->classify('10.0.0.5')['private']);
        $this->assertTrue($svc->classify('192.168.1.1')['private']);
        $this->assertTrue($svc->classify('172.16.5.5')['private']);
        $this->assertTrue($svc->classify('100.100.0.1')['private']); // CGNAT
        $this->assertTrue($svc->classify(null)['private']);
        $this->assertTrue($svc->classify('not-an-ip')['private']);
    }

    public function test_classify_marks_public_addresses_as_not_private(): void
    {
        $svc = new SecurityIntel();

        $intel = $svc->classify('203.0.113.5');
        $this->assertFalse($intel['private']);
        $this->assertSame('203.0.113.5', $intel['ip']);
        // No DB configured in tests → country/asn remain null
        $this->assertNull($intel['country']);
        $this->assertNull($intel['asn']);
    }

    public function test_login_audit_payload_includes_intel_block(): void
    {
        User::factory()->create([
            'email' => 'intel@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->postJson('/api/auth/login', [
                'email' => 'intel@example.com',
                'password' => 'correct-password',
            ])->assertOk();

        $log = AuditLog::where('event', 'auth.login_success')->latest('id')->first();
        $payload = json_decode($log->new_values, true);

        $this->assertArrayHasKey('intel', $payload);
        $this->assertSame('198.51.100.10', $payload['intel']['ip']);
        $this->assertFalse($payload['intel']['private']);
    }

    public function test_anomalies_endpoint_returns_country_breakdown(): void
    {
        $user = User::factory()->create(['role' => 'officer']);

        AuditLog::create([
            'user_id' => $user->id,
            'event' => 'auth.failed_login',
            'ip_address' => '203.0.113.1',
            'new_values' => json_encode(['intel' => ['country' => 'TZ']]),
        ]);
        AuditLog::create([
            'user_id' => $user->id,
            'event' => 'auth.failed_login',
            'ip_address' => '203.0.113.2',
            'new_values' => json_encode(['intel' => ['country' => 'TZ']]),
        ]);
        AuditLog::create([
            'user_id' => $user->id,
            'event' => 'auth.failed_login',
            'ip_address' => '203.0.113.3',
            'new_values' => json_encode(['intel' => ['country' => 'KE']]),
        ]);
        AuditLog::create([
            'user_id' => $user->id,
            'event' => 'auth.failed_login',
            'ip_address' => '127.0.0.1',
            'new_values' => json_encode(['intel' => ['country' => null]]),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $body = $this->getJson('/api/system/security/anomalies?days=7')->assertOk()->json();

        $countries = collect($body['top_failed_countries'])->pluck('failed_count', 'country');
        $this->assertSame(2, $countries['TZ']);
        $this->assertSame(1, $countries['KE']);
        $this->assertSame(1, $countries['UNKNOWN']);
    }
}
