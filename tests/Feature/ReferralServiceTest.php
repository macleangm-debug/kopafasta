<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ReferralWallet;
use App\Models\User;
use App\Services\GrowthPointsService;
use App\Services\LoyaltyPointsService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create(array_merge([
            'user_id'         => $user->id,
            'customer_number' => 'C-TEST'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Test',
            'last_name'       => 'Member',
            'phone'           => '+255700'.random_int(100000, 999999),
        ], $overrides));
    }

    public function test_quote_does_not_discount_by_default(): void
    {
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-REF001']);
        $referred = $this->makeCustomer(['referred_by_customer_id' => $referrer->id]);
        ReferralWallet::create(['customer_id' => $referred->id, 'balance' => 5000]);

        $service = app(ReferralService::class);
        $quote = $service->quoteFee($referred, 10000, true, 'application_fee');

        $this->assertSame(0.0, $quote['discount']);
        $this->assertSame(10000.0, $quote['after_discount']);
        $this->assertSame(5000.0, $quote['wallet_applied']);
        $this->assertSame(5000.0, $quote['cash_due']);
        $this->assertSame(0, $quote['referrer_points']);
        $this->assertSame(0.0, $quote['commission']);
    }

    public function test_settle_fee_debits_wallet_without_membership_commission(): void
    {
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-REF002']);
        $referred = $this->makeCustomer(['referred_by_customer_id' => $referrer->id]);
        ReferralWallet::create(['customer_id' => $referred->id, 'balance' => 2000]);

        $service = app(ReferralService::class);
        $service->settleFee($referred, 10000, true, 'registration_fee');

        $this->assertSame(0.0, (float) $service->wallet($referred)->fresh()->balance);
        $this->assertSame(0.0, (float) $service->wallet($referrer)->fresh()->balance);
    }

    public function test_attach_referrer_awards_register_points(): void
    {
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-REF003', 'loyalty_points' => 0]);
        $invitee = $this->makeCustomer();

        app(ReferralService::class)->attachReferrer($invitee, 'KPF-REF003');

        $this->assertSame($referrer->id, $invitee->fresh()->referred_by_customer_id);
        $this->assertSame(5, app(LoyaltyPointsService::class)->balance($referrer->fresh()));
    }

    public function test_first_application_fee_awards_application_points(): void
    {
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-REF004', 'loyalty_points' => 0]);
        $invitee = $this->makeCustomer(['referred_by_customer_id' => $referrer->id]);

        app(GrowthPointsService::class)->awardFirstApplicationFee($invitee);

        $this->assertSame(25, app(LoyaltyPointsService::class)->balance($referrer->fresh()));
        app(GrowthPointsService::class)->awardFirstApplicationFee($invitee);
        $this->assertSame(25, app(LoyaltyPointsService::class)->balance($referrer->fresh()));
    }

    public function test_demo_accounts_do_not_earn_referral_points(): void
    {
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-DEMO1', 'customer_number' => 'DEMO-001']);
        $invitee = $this->makeCustomer();

        app(ReferralService::class)->attachReferrer($invitee, 'KPF-DEMO1');

        $this->assertSame($referrer->id, $invitee->fresh()->referred_by_customer_id);
        $this->assertSame(0, app(LoyaltyPointsService::class)->balance($referrer->fresh()));
    }

    public function test_share_message_does_not_promise_membership_discount(): void
    {
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-SHARE1']);
        $message = app(ReferralService::class)->shareMessage($referrer, 'en');

        $this->assertStringNotContainsString('membership fee', strtolower($message));
        $this->assertStringContainsString('reward points', strtolower($message));
    }
}
