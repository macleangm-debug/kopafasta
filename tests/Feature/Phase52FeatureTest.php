<?php

namespace Tests\Feature;

use App\Models\AffiliateEvent;
use App\Models\Customer;
use App\Models\MarketplaceAsset;
use App\Models\Partner;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase52FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_id_columns_replace_vendor_id(): void
    {
        $this->assertTrue(Schema::hasColumn('marketplace_assets', 'partner_id'));
        $this->assertFalse(Schema::hasColumn('marketplace_assets', 'vendor_id'));
        $this->assertTrue(Schema::hasColumn('customers', 'affiliate_partner_id'));
        $this->assertFalse(Schema::hasColumn('customers', 'affiliate_vendor_id'));
    }

    public function test_vendor_id_accessor_maps_to_partner_id_on_create(): void
    {
        $partner = Partner::create([
            'vendor_number' => 'PTR-P52-001',
            'name'          => 'Supplier P52',
            'category'      => 'supplier',
            'status'        => 'active',
            'phone'         => '255712345899',
        ]);

        $asset = MarketplaceAsset::create([
            'vendor_id'       => $partner->id,
            'slug'            => 'test-asset-p52',
            'title'           => 'Test Asset P52',
            'category'        => 'vehicle',
            'supplier_name'   => $partner->name,
            'asset_value'     => 1000000,
            'supplier_deposit'=> 100000,
            'customer_deposit'=> 110000,
            'is_active'       => true,
        ]);

        $this->assertDatabaseHas('marketplace_assets', [
            'id'         => $asset->id,
            'partner_id' => $partner->id,
        ]);
        $this->assertSame($partner->id, $asset->fresh()->vendor_id);
        $this->assertSame($partner->id, $asset->fresh()->vendor?->id);
    }

    public function test_affiliate_partner_id_accessor_on_customer(): void
    {
        $affiliate = Vendor::create([
            'vendor_number' => 'PTR-P52-AFF',
            'name'          => 'Affiliate P52',
            'category'      => 'affiliate',
            'status'        => 'active',
            'phone'         => '255712345890',
            'affiliate_code'=> 'AFFP52',
        ]);

        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id'             => $user->id,
            'customer_number'     => 'CU-P52-001',
            'type'                => 'individual',
            'status'              => 'active',
            'first_name'          => 'Aff',
            'last_name'           => 'Customer',
            'phone'               => '255712345891',
            'affiliate_vendor_id' => $affiliate->id,
        ]);

        $this->assertDatabaseHas('customers', [
            'id'                   => $customer->id,
            'affiliate_partner_id' => $affiliate->id,
        ]);
        $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);
        $this->assertSame($affiliate->id, $customer->fresh()->affiliateVendor?->id);
    }

    public function test_affiliate_event_vendor_id_accessor_resolves_partner(): void
    {
        $partner = Partner::create([
            'vendor_number' => 'PTR-P52-002',
            'name'          => 'Event Partner',
            'category'      => 'affiliate',
            'status'        => 'active',
            'phone'         => '255712345892',
            'affiliate_code'=> 'AFFP522',
        ]);

        $event = AffiliateEvent::create([
            'vendor_id'  => $partner->id,
            'event_type' => 'click',
        ]);

        $this->assertDatabaseHas('affiliate_events', [
            'id'         => $event->id,
            'partner_id' => $partner->id,
        ]);
        $this->assertSame($partner->id, $event->fresh()->vendor?->id);
    }
}
