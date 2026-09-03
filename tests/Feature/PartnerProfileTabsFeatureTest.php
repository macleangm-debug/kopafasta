<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\PartnerProfileTabs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerProfileTabsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_sets_follow_partner_type(): void
    {
        $tabs = app(PartnerProfileTabs::class);

        $valuer = Vendor::create(['name' => 'Valuer A', 'category' => 'valuer', 'status' => 'active', 'vendor_number' => 'PT-VL-TABS-1']);
        $this->assertSame([
            'profile', 'jobs', 'performance', 'membership', 'compliance', 'documents', 'agreements', 'history', 'portal', 'account',
        ], array_keys($tabs->tabs($valuer)));

        $collector = Vendor::create(['name' => 'Collector A', 'category' => 'debt_collector', 'status' => 'active', 'vendor_number' => 'PT-DC-TABS-1']);
        $this->assertSame([
            'profile', 'cases', 'performance', 'compliance', 'documents', 'agreements', 'history', 'portal', 'account',
        ], array_keys($tabs->tabs($collector)));

        $affiliate = Vendor::create(['name' => 'Affiliate A', 'category' => 'affiliate', 'status' => 'active', 'vendor_number' => 'PT-AF-TABS-1']);
        $this->assertSame(['profile', 'pipeline', 'performance', 'membership', 'agreements', 'portal', 'account'], array_keys($tabs->tabs($affiliate)));

        $supplier = Vendor::create(['name' => 'Supplier A', 'category' => 'supplier', 'status' => 'active', 'vendor_number' => 'PT-SU-TABS-1']);
        $this->assertSame(['profile', 'listings', 'performance', 'portal', 'account'], array_keys($tabs->tabs($supplier)));

        $capital = Vendor::create(['name' => 'Capital A', 'category' => 'capital', 'status' => 'active', 'vendor_number' => 'PT-CP-TABS-1']);
        $this->assertSame(['profile', 'capital', 'portal', 'account'], array_keys($tabs->tabs($capital)));

        $yard = Vendor::create(['name' => 'Yard A', 'category' => 'yard', 'status' => 'active', 'vendor_number' => 'PT-YD-TABS-1']);
        $this->assertSame(['profile', 'portal', 'account'], array_keys($tabs->tabs($yard)));
    }

    public function test_affiliate_profile_uses_pipeline_not_jobs(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $affiliate = Vendor::create([
            'name' => 'Pipeline Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'affiliate_code' => 'PIPE01',
            'vendor_number' => 'PT-AF-PIPE-1',
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $affiliate))
            ->assertOk()
            ->assertSee('Business', false)
            ->assertSee('Performance', false)
            ->assertSee('Membership', false)
            ->assertSee('Agreements', false)
            ->assertSee('Profile', false)
            ->assertDontSee('>Affiliate</', false)
            ->assertDontSee('Update lifecycle', false)
            ->assertDontSee('Approve payment', false)
            ->assertDontSee('Override risk flag', false)
            ->assertDontSee('Run fraud scan', false)
            ->getContent();

        $this->assertStringContainsString("setTab('pipeline')", $html);
        $this->assertStringNotContainsString("setTab('jobs')", $html);
        $this->assertStringNotContainsString("setTab('affiliate')", $html);
    }

    public function test_capital_profile_shows_capital_tab_not_jobs(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $partner = Vendor::create([
            'name' => 'Lake Capital',
            'category' => 'capital',
            'status' => 'active',
            'vendor_number' => 'PT-CP-LAKE-1',
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $partner))
            ->assertOk()
            ->assertSee('Capital', false)
            ->assertSee('No capital book yet', false)
            ->getContent();

        $this->assertStringContainsString("setTab('capital')", $html);
        $this->assertStringNotContainsString("setTab('jobs')", $html);
        $this->assertStringNotContainsString("setTab('performance')", $html);
    }

    public function test_yard_profile_omits_jobs_and_performance(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $partner = Vendor::create([
            'name' => 'Kurasini Yard',
            'category' => 'yard',
            'status' => 'active',
            'vendor_number' => 'PT-YD-KURA-1',
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $partner))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("setTab('profile')", $html);
        $this->assertStringNotContainsString("setTab('jobs')", $html);
        $this->assertStringNotContainsString("setTab('performance')", $html);
    }
}
