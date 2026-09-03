<?php

namespace Tests\Feature;

use App\Models\AffiliateEvent;
use App\Models\Customer;
use App\Models\PartnerPayment;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateAttributionService;
use App\Services\AffiliateCommissionCalculatorService;
use App\Services\AffiliateCommissionWalletService;
use App\Services\AffiliateService;
use App\Services\PartnerSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class Phase66AffiliatePhase3FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function affiliatePartner(?User $user = null): Vendor
    {
        $user ??= User::factory()->create(['role' => 'vendor']);

        return Vendor::create([
            'user_id'                    => $user->id,
            'vendor_number'              => 'AFF-P66',
            'name'                       => 'Affiliate P66',
            'category'                   => 'affiliate',
            'status'                     => 'active',
            'phone'                      => '255712346660',
            'affiliate_code'             => 'AFFP66',
            'affiliate_kyc_status'       => 'verified',
            'affiliate_lifecycle_status' => 'active',
            'membership_status'          => 'active',
            'membership_started_at'      => now()->subMonth(),
            'membership_expires_at'      => now()->addYear(),
        ]);
    }

    public function test_attribution_captured_on_affiliate_redirect(): void
    {
        $affiliate = $this->affiliatePartner();

        $this->get('/aff/AFFP66?utm_source=facebook&utm_campaign=launch&utm_medium=social')
            ->assertRedirect();

        $this->assertDatabaseHas('affiliate_events', [
            'partner_id'   => $affiliate->id,
            'event_type'   => 'click',
            'utm_source'   => 'facebook',
            'utm_campaign' => 'launch',
            'utm_medium'   => 'social',
        ]);

        $session = session(AffiliateAttributionService::SESSION_KEY, []);
        $this->assertSame('facebook', $session['utm_source'] ?? null);
    }

    public function test_attribution_service_detects_device_type(): void
    {
        $service = app(AffiliateAttributionService::class);

        $this->assertSame('mobile', $service->detectDevice('Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)'));
        $this->assertSame('desktop', $service->detectDevice('Mozilla/5.0 (Windows NT 10.0; Win64; x64)'));
    }

    public function test_tiered_commission_uses_referral_count(): void
    {
        Setting::setMany([
            'affiliates.commission_mode' => 'tiered',
            'affiliates.commission_tiers' => [
                ['min_count' => 1, 'max_count' => 10, 'type' => 'fixed', 'amount' => 1000],
                ['min_count' => 11, 'max_count' => null, 'type' => 'fixed', 'amount' => 1500],
            ],
        ]);

        $affiliate = $this->affiliatePartner();

        for ($i = 0; $i < 12; $i++) {
            AffiliateEvent::create([
                'vendor_id'  => $affiliate->id,
                'event_type' => 'registration',
            ]);
        }

        $calculator = app(AffiliateCommissionCalculatorService::class);
        $amount = $calculator->calculate($affiliate, 10_000, 'registration_fee');

        $this->assertSame(1500.0, $amount);
    }

    public function test_hybrid_commission_adds_fixed_and_percent(): void
    {
        Setting::setMany([
            'affiliates.commission_mode'   => 'hybrid',
            'affiliates.hybrid_fixed_amount' => 500,
            'affiliates.hybrid_percent'    => 10,
        ]);

        $affiliate = $this->affiliatePartner();
        $amount = app(AffiliateCommissionCalculatorService::class)
            ->calculate($affiliate, 10_000, 'registration_fee');

        $this->assertSame(1500.0, $amount);
    }

    public function test_affiliate_commission_wallet_dispute(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $affiliate = $this->affiliatePartner($user);

        $payment = app(PartnerSettlementService::class)->accrue(
            $affiliate,
            5_000,
            AffiliateCommissionWalletService::SOURCE_TYPE,
            99,
            'Affiliate commission on registration fee',
        );

        $this->actingAs($user)
            ->post(route('site.affiliate.wallet.dispute', $payment), [
                'reason' => 'Wrong fee type applied',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('disputed', $payment->fresh()->status);
        $this->assertSame('Wrong fee type applied', $payment->fresh()->dispute_reason);
    }

    public function test_affiliate_portal_dashboard_accessible(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $this->affiliatePartner($user);

        $this->actingAs($user)
            ->get(route('site.affiliate.dashboard'))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.dashboard_title'), false)
            ->assertSee('AFFP66', false);
    }

    public function test_partner_home_redirects_affiliate_to_dedicated_portal(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $this->affiliatePartner($user);

        $this->actingAs($user)
            ->get(route('site.partner.dashboard'))
            ->assertRedirect(route('site.affiliate.dashboard'));
    }

    public function test_registration_attaches_affiliate_with_attribution(): void
    {
        $affiliate = $this->affiliatePartner();

        session([
            'affiliate_code' => 'AFFP66',
            AffiliateAttributionService::SESSION_KEY => [
                'utm_source'  => 'instagram',
                'utm_campaign'=> 'influencer',
                'device_type' => 'mobile',
            ],
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-P66-ATTR',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Referred',
            'last_name'       => 'Borrower',
            'phone'           => '255712346661',
        ]);

        app(AffiliateService::class)->attachAffiliate($customer, 'AFFP66');

        $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);

        $this->assertDatabaseHas('affiliate_events', [
            'partner_id'    => $affiliate->id,
            'event_type'    => 'registration',
            'customer_id'   => $customer->id,
            'utm_source'    => 'instagram',
            'utm_campaign'  => 'influencer',
            'device_type'   => 'mobile',
        ]);
    }
}
