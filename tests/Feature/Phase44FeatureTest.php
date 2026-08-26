<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LocationCountry;
use App\Models\LocationDistrict;
use App\Models\LocationRegion;
use App\Models\User;
use App\Models\Vendor;
use App\Services\LocationLookupService;
use App\Services\PartnerMatchingService;
use Database\Seeders\LocationMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\CompletesPartnerJobs;
use Tests\TestCase;

class Phase44FeatureTest extends TestCase
{
    use CompletesPartnerJobs;
    use RefreshDatabase;

    public function test_unified_partner_supplier_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('site.partner.supplier.assets'));
        $this->assertTrue(Route::has('site.supplier.assets'));
    }

    public function test_nationwide_valuer_matches_any_region(): void
    {
        $this->completePartnerForJobs(Vendor::create([
            'vendor_number'  => 'PTR-P44-001',
            'name'           => 'Nationwide Valuer',
            'category'       => 'valuer',
            'status'         => 'active',
            'coverage_type'  => 'nationwide',
            'regions'        => [],
        ]));

        $matches = app(PartnerMatchingService::class)->valuersForRegion('Mwanza');

        $this->assertTrue($matches->contains('name', 'Nationwide Valuer'));
    }

    public function test_location_master_seeder_populates_tanzania_tree(): void
    {
        $this->seed(LocationMasterSeeder::class);

        $tree = app(LocationLookupService::class)->treeForCountry('TZ');

        $this->assertArrayHasKey('Dar es Salaam', $tree);
        $this->assertContains('Ilala', $tree['Dar es Salaam']);

        $country = LocationCountry::query()->where('code', 'TZ')->first();
        $this->assertNotNull($country);
        $this->assertGreaterThan(0, LocationRegion::query()->where('country_id', $country->id)->count());
        $this->assertGreaterThan(0, LocationDistrict::query()->count());
    }

    public function test_affiliate_partner_still_does_not_require_regions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name'           => 'Nationwide Affiliate 44',
                'category'       => 'affiliate',
                'status'         => 'active',
                'phone'          => '255712345872',
                'coverage_type'  => 'nationwide',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('partners', [
            'name'     => 'Nationwide Affiliate 44',
            'category' => 'affiliate',
        ]);
    }

    public function test_partner_portal_config_declares_unified_path(): void
    {
        $this->assertSame('/partner', config('partners.unified_path'));
    }
}
