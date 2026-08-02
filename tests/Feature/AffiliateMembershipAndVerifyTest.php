<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateMembershipAndVerifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_config_defaults(): void
    {
        $cfg = AffiliateMembershipService::config();

        $this->assertTrue($cfg['enabled']);
        $this->assertSame(50000.0, $cfg['fee_amount']);
        $this->assertSame(48, $cfg['grace_period_hours']);
        $this->assertSame(365, $cfg['duration_days']);
    }

    public function test_activate_membership_unlocks_sharing(): void
    {
        $vendor = Vendor::query()->create([
            'name' => 'Test Affiliate',
            'partner_number' => 'PTR-AFF-TEST1',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '+255710000999',
            'affiliate_code' => 'KPA-TEST99',
            'affiliate_kyc_status' => 'verified',
        ]);

        $service = app(AffiliateMembershipService::class);
        $this->assertFalse($service->isActive($vendor));
        $this->assertFalse($service->isSharingAllowed($vendor));

        $service->activate($vendor, 'AFF-TEST-REF');
        $vendor->refresh();

        $this->assertTrue($service->isActive($vendor));
        $this->assertTrue($service->isSharingAllowed($vendor));
        $this->assertSame('active', $vendor->membership_status);
    }

    public function test_public_verify_index_and_phone_lookup(): void
    {
        Vendor::query()->create([
            'name' => 'Phone Affiliate',
            'partner_number' => 'PTR-AFF-PHONE1',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '255710000888',
            'affiliate_code' => 'KPA-PHONE1',
            'affiliate_kyc_status' => 'verified',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $this->get(route('site.affiliate.verify.index'))
            ->assertOk()
            ->assertSee('Look up an affiliate', false);

        $this->post(route('site.affiliate.verify.lookup'), [
            'phone' => '+255710000888',
        ])->assertOk()
            ->assertSee('Phone Affiliate', false)
            ->assertSee('KPA-PHONE1', false);
    }
}
