<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\CrbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase10FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_crb_settings_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.settings.crb'));
        $this->assertTrue(Route::has('admin.settings.crb.save'));
        $this->assertTrue(Route::has('admin.settings.crb.test'));
    }

    public function test_crb_settings_page_loads_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.crb'))
            ->assertRedirect(route('admin.settings.integrations.partner', [
                'partner' => 'crb',
                'tab' => 'configuration',
            ]));
    }

    public function test_crb_stub_lookup_succeeds_with_sample_nida(): void
    {
        $sample = config('crb_samples.scenarios.verified', []);
        $nida = (string) ($sample['nida'] ?? '19810713-00001-23456-78');

        $result = app(CrbService::class)->verifyConsumerIdentity(
            $nida,
            $sample['full_name'] ?? null,
            $sample['date_of_birth'] ?? null,
        );

        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->fullName);
    }

    public function test_settings_hub_lists_crb_integration_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('CRB integration', false);
    }

    public function test_admin_partner_edit_page_uses_partner_label(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = Vendor::create([
            'vendor_number' => 'PTR-P10-001',
            'name' => 'Phase 10 Partner',
            'category' => 'valuer',
            'status' => 'active',
            'phone' => '255712345692',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.edit', $partner))
            ->assertOk()
            ->assertSee('Edit partner', false);
    }
}
