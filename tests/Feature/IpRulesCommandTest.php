<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IpRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class IpRulesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_add_creates_rule_and_audit(): void
    {
        $this->artisan('security:ip-rules', [
            'action' => 'add',
            'cidr' => '203.0.113.0/24',
            '--mode' => 'deny',
            '--reason' => 'abuse',
        ])->assertSuccessful();

        $this->assertDatabaseHas('ip_rules', ['cidr' => '203.0.113.0/24', 'mode' => 'deny', 'reason' => 'abuse']);
        $this->assertTrue(AuditLog::where('event', 'cli.ip_rule_created')->exists());
    }

    public function test_add_rejects_invalid_cidr(): void
    {
        $this->artisan('security:ip-rules', [
            'action' => 'add',
            'cidr' => 'not-a-cidr',
            '--mode' => 'deny',
        ])->assertFailed();
    }

    public function test_add_rejects_opposite_mode_without_force(): void
    {
        IpRule::create(['cidr' => '198.51.100.0/24', 'mode' => 'allow']);

        $this->artisan('security:ip-rules', [
            'action' => 'add',
            'cidr' => '198.51.100.0/24',
            '--mode' => 'deny',
        ])->assertFailed();

        $this->assertDatabaseMissing('ip_rules', ['cidr' => '198.51.100.0/24', 'mode' => 'deny']);
    }

    public function test_add_with_force_overrides_opposite_mode_check(): void
    {
        IpRule::create(['cidr' => '198.51.100.0/24', 'mode' => 'allow']);

        $this->artisan('security:ip-rules', [
            'action' => 'add',
            'cidr' => '198.51.100.0/24',
            '--mode' => 'deny',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('ip_rules', ['cidr' => '198.51.100.0/24', 'mode' => 'deny']);
    }

    public function test_remove_deletes_rule_and_audit(): void
    {
        IpRule::create(['cidr' => '10.0.0.0/8', 'mode' => 'deny']);

        $this->artisan('security:ip-rules', [
            'action' => 'remove',
            'cidr' => '10.0.0.0/8',
            '--mode' => 'deny',
        ])->assertSuccessful();

        $this->assertDatabaseMissing('ip_rules', ['cidr' => '10.0.0.0/8', 'mode' => 'deny']);
        $this->assertTrue(AuditLog::where('event', 'cli.ip_rule_deleted')->exists());
    }

    public function test_list_runs_when_empty_and_with_rules(): void
    {
        $this->artisan('security:ip-rules', ['action' => 'list'])->assertSuccessful();

        IpRule::create(['cidr' => '127.0.0.1/32', 'mode' => 'allow', 'reason' => 'office']);
        $this->artisan('security:ip-rules', ['action' => 'list'])->assertSuccessful();
    }

    public function test_seeder_creates_rules_from_config(): void
    {
        config()->set('security.deny_cidrs', '203.0.113.0/24, 198.51.100.5');
        config()->set('security.allow_cidrs', ['127.0.0.1/32']);

        $this->seed(\Database\Seeders\IpRuleSeeder::class);

        $this->assertDatabaseHas('ip_rules', ['cidr' => '203.0.113.0/24', 'mode' => 'deny']);
        $this->assertDatabaseHas('ip_rules', ['cidr' => '198.51.100.5', 'mode' => 'deny']);
        $this->assertDatabaseHas('ip_rules', ['cidr' => '127.0.0.1/32', 'mode' => 'allow']);
    }
}
