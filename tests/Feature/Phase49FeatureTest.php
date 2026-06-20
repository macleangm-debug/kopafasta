<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase49FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendors_table_is_renamed_to_partners(): void
    {
        $this->assertTrue(Schema::hasTable('partners'));
        $this->assertFalse(Schema::hasTable('vendors'));
    }

    public function test_partner_and_vendor_models_share_partners_table(): void
    {
        $partner = Partner::create([
            'vendor_number' => 'PTR-P49-001',
            'name'            => 'Unified Partner',
            'category'        => 'supplier',
            'status'          => 'active',
            'phone'           => '255712345896',
        ]);

        $this->assertDatabaseHas('partners', [
            'id'   => $partner->id,
            'name' => 'Unified Partner',
        ]);

        $viaAlias = Vendor::find($partner->id);
        $this->assertNotNull($viaAlias);
        $this->assertSame('Unified Partner', $viaAlias->name);
        $this->assertSame('partners', $viaAlias->getTable());
    }

    public function test_affiliate_event_foreign_key_still_resolves_partner(): void
    {
        $partner = Partner::create([
            'vendor_number' => 'PTR-P49-002',
            'name'            => 'Affiliate Partner',
            'category'        => 'affiliate',
            'status'          => 'active',
            'phone'           => '255712345897',
            'affiliate_code'  => 'AFFP49',
        ]);

        $event = \App\Models\AffiliateEvent::create([
            'vendor_id'  => $partner->id,
            'event_type' => 'click',
        ]);

        $this->assertSame($partner->id, $event->fresh()->vendor?->id);
        $this->assertInstanceOf(Vendor::class, $event->vendor);
    }
}
