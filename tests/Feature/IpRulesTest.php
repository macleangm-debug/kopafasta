<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IpRule;
use App\Models\User;
use App\Services\IpRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IpRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_admin_can_create_and_list_ip_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/system/security/ip-rules', [
            'cidr' => '203.0.113.0/24',
            'mode' => 'deny',
            'reason' => 'Known abuse',
        ])->assertStatus(201)
            ->assertJsonFragment(['cidr' => '203.0.113.0/24', 'mode' => 'deny']);

        $this->getJson('/api/system/security/ip-rules')
            ->assertOk()
            ->assertJsonFragment(['cidr' => '203.0.113.0/24']);

        $this->assertTrue(AuditLog::where('event', 'admin.ip_rule_created')->exists());
    }

    public function test_non_admin_cannot_manage_ip_rules(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($manager);

        $this->getJson('/api/system/security/ip-rules')->assertStatus(403);
        $this->postJson('/api/system/security/ip-rules', [
            'cidr' => '10.0.0.0/8',
            'mode' => 'allow',
        ])->assertStatus(403);
    }

    public function test_admin_rejects_invalid_cidr(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/system/security/ip-rules', [
            'cidr' => 'not-a-cidr',
            'mode' => 'deny',
        ])->assertStatus(422);
    }

    public function test_admin_can_delete_ip_rule(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $rule = IpRule::create(['cidr' => '198.51.100.5', 'mode' => 'deny']);

        $this->deleteJson("/api/system/security/ip-rules/{$rule->id}")->assertOk();
        $this->assertDatabaseMissing('ip_rules', ['id' => $rule->id]);
        $this->assertTrue(AuditLog::where('event', 'admin.ip_rule_deleted')->exists());
    }

    public function test_denied_ip_short_circuits_login_with_403(): void
    {
        IpRule::create(['cidr' => '127.0.0.0/8', 'mode' => 'deny', 'reason' => 'lab']);

        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'correct-password',
        ])->assertStatus(403);

        $this->assertTrue(AuditLog::where('event', 'auth.ip_denied')->exists());
        $this->assertFalse(AuditLog::where('event', 'auth.failed_login')->exists());
        $this->assertFalse(AuditLog::where('event', 'auth.login_success')->exists());
    }

    public function test_allow_listed_ip_bypasses_anomaly_ip_block(): void
    {
        IpRule::create(['cidr' => '127.0.0.1/32', 'mode' => 'allow', 'reason' => 'office']);

        // Pre-populate an IP block in the cache
        Cache::put('sec:ip_blocked:127.0.0.1', 1, now()->addHour());
        Cache::put('sec:ip_blocked:127.0.0.1:expires', now()->addHour()->timestamp, now()->addHour());

        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
        ]);

        // Should NOT 429 because allow rule exempts the IP
        $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'correct-password',
        ])->assertOk();
    }

    public function test_cidr_matcher_handles_ipv4_and_ipv6(): void
    {
        $svc = app(IpRuleService::class);

        $this->assertTrue($svc->ipMatchesCidr('10.1.2.3', '10.0.0.0/8'));
        $this->assertFalse($svc->ipMatchesCidr('11.1.2.3', '10.0.0.0/8'));
        $this->assertTrue($svc->ipMatchesCidr('192.168.1.5', '192.168.1.5'));
        $this->assertTrue($svc->ipMatchesCidr('2001:db8::1', '2001:db8::/32'));
        $this->assertFalse($svc->ipMatchesCidr('2001:dead::1', '2001:db8::/32'));
        $this->assertFalse($svc->ipMatchesCidr('10.0.0.1', '2001:db8::/32'));
    }
}
