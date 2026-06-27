<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase21FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_partner_gets_pt_code_format(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name'     => 'Phase 21 Partner',
                'category' => 'supplier',
                'status'   => 'active',
                'phone'    => '255712345815',
                'regions'  => ['Dar es Salaam'],
            ])
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'Phase 21 Partner')->first();

        $this->assertNotNull($partner);
        $this->assertMatchesRegularExpression('/^PT-SP-TZ-[A-Z0-9]{4}$/', $partner->vendor_number);
    }

    public function test_legacy_vendors_index_redirects_to_partners_hub(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/vendors')
            ->assertRedirect('/admin/partners/all');
    }

    public function test_legacy_vendors_create_redirects_to_partners_create(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/vendors/create')
            ->assertRedirect('/admin/partners/create');
    }

    public function test_admin_partner_tasks_page_uses_partner_labels(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.tasks'))
            ->assertOk()
            ->assertSee('Partner tasks', false)
            ->assertSee('Search partner or task type', false);
    }

    public function test_swahili_marketplace_uses_apply_for_asset_copy(): void
    {
        $this->withSession(['locale' => 'sw'])
            ->get(route('site.home'))
            ->assertOk();

        $this->assertSame('Omba mali', __('borrower.marketplace.apply_asset', [], 'sw'));
        $this->assertSame('Omba mali', __('borrower.marketplace.reserve_title', [], 'sw'));
    }
}
