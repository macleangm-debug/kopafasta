<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoyaltyRedemption;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CustomerPaymentService;
use App\Services\GrowthPointsService;
use App\Services\LoyaltyPointsService;
use App\Services\LoyaltyRedemptionService;
use App\Services\PayInService;
use App\Services\PaymentGateService;
use App\Services\Plus\PlusService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GrowthRewardsCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create(array_merge([
            'user_id'         => $user->id,
            'customer_number' => 'C-GRW'.random_int(1000, 9999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Growth',
            'last_name'       => 'Member',
            'phone'           => '+255700'.random_int(100000, 999999),
            'loyalty_points'  => 0,
            'country_code'    => 'TZ',
        ], $overrides));
    }

    public function test_legacy_referral_discount_percent_does_not_change_checkout(): void
    {
        Setting::set('referrals.discount_percent', 10);
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-LEGACY1']);
        $invitee = $this->makeCustomer(['referred_by_customer_id' => $referrer->id]);

        $quote = app(ReferralService::class)->quoteFee($invitee, 10000, false, 'application_fee');
        $this->assertSame(0.0, $quote['discount']);
        $this->assertSame(10000.0, $quote['cash_due']);

        $gate = app(PaymentGateService::class)->quote($invitee->fresh(), 10000, 'application_fee');
        $this->assertSame(0.0, (float) $gate['referral_discount']);
        $this->assertSame(10000.0, (float) $gate['cash_due']);
        $this->assertSame(10000.0, (float) $gate['base']);
    }

    public function test_account_owner_points_go_to_invitee_not_referrer(): void
    {
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-OWN1', 'loyalty_points' => 0]);
        $invitee = $this->makeCustomer(['referred_by_customer_id' => $referrer->id, 'loyalty_points' => 0]);

        app(GrowthPointsService::class)->awardOwnerAction($invitee, 'complete_profile');

        $this->assertSame(0, app(LoyaltyPointsService::class)->balance($referrer->fresh()));
        $this->assertSame(10, app(LoyaltyPointsService::class)->balance($invitee->fresh()));
    }

    public function test_demo_accounts_cannot_earn_or_unlock_rewards(): void
    {
        $demo = $this->makeCustomer(['customer_number' => 'DEMO-GRW1', 'loyalty_points' => 500]);

        $this->assertSame(0, app(LoyaltyPointsService::class)->earn($demo->fresh(), 'complete_profile'));
        $this->assertSame(500, app(LoyaltyPointsService::class)->balance($demo->fresh()));

        $this->expectException(\InvalidArgumentException::class);
        app(LoyaltyRedemptionService::class)->redeem($demo->fresh(), 'application_fee_10');
    }

    public function test_plus_only_reward_unlocks_for_active_plus_member(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 400]);
        app(PlusService::class)->grantComplimentary($customer, 'QA plus-only', null, 30);

        $redemption = app(LoyaltyRedemptionService::class)->redeem($customer->fresh(), 'plus_application_fee_waive');

        $this->assertSame('active', $redemption->status);
        $this->assertSame('plus_application_fee_waive', $redemption->option_key);
    }

    public function test_priority_support_marks_new_tickets_urgent(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 200]);
        app(LoyaltyRedemptionService::class)->redeem($customer->fresh(), 'priority_support');
        $this->assertNotNull(app(LoyaltyRedemptionService::class)->activePrioritySupport($customer->fresh()));

        $this->actingAs($customer->user)
            ->post(route('site.feedback.post'), [
                'category' => 'suggestion',
                'name' => 'Growth Member',
                'subject' => 'Need help',
                'message' => 'Please look at my account.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('support_tickets', [
            'customer_id' => $customer->id,
            'priority' => 'urgent',
        ]);
        $this->assertTrue(SupportTicket::query()->where('customer_id', $customer->id)->exists());
    }

    public function test_checkout_keeps_gross_obligation_and_records_net_separately(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 200]);
        $redemption = app(LoyaltyRedemptionService::class)->redeem($customer->fresh(), 'application_fee_10');

        $payment = CustomerPayment::create([
            'reference' => 'PAY-GRW-GROSS',
            'customer_id' => $customer->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 10000,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
        ]);

        app(CustomerPaymentService::class)->applyCheckoutBenefits($payment, true);
        $payment->refresh();

        $this->assertSame(10000.0, (float) $payment->amount);
        $this->assertSame(10000.0, (float) data_get($payment->provider_meta, 'pricing.gross'));
        $this->assertSame(1000.0, (float) data_get($payment->provider_meta, 'pricing.loyalty_discount'));
        $this->assertSame(9000.0, (float) data_get($payment->provider_meta, 'pricing.net_payable'));
        $this->assertSame('loyalty_reward', data_get($payment->provider_meta, 'pricing.discount_source'));
        $this->assertSame($redemption->id, (int) data_get($payment->provider_meta, 'pricing.loyalty_redemption_id'));
        $this->assertSame('active', $redemption->fresh()->status);
    }

    public function test_failed_psp_does_not_consume_the_reward(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 200]);
        $redemption = app(LoyaltyRedemptionService::class)->redeem($customer->fresh(), 'application_fee_10');

        $payment = CustomerPayment::create([
            'reference' => 'PAY-GRW-FAIL',
            'customer_id' => $customer->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 10000,
            'currency' => 'TZS',
            'status' => 'processing',
            'provider' => 'payin',
            'provider_ref' => 'PAYREF-GRW-FAIL',
            'mobile_number' => '255711111111',
            'provider_meta' => [
                'pricing' => [
                    'gross' => 10000,
                    'loyalty_discount' => 1000,
                    'loyalty_redemption_id' => $redemption->id,
                    'net_payable' => 9000,
                    'apply_reward' => true,
                ],
            ],
        ]);

        $payIn = Mockery::mock(PayInService::class);
        $payIn->shouldReceive('status')->once()->with('PAYREF-GRW-FAIL')->andReturn([
            'ok' => true,
            'request_ref' => 'PAYREF-GRW-FAIL',
            'status' => 'failed',
            'message' => 'Customer cancelled',
            'raw' => [],
        ]);
        $this->app->instance(PayInService::class, $payIn);

        $this->actingAs($customer->user)
            ->getJson(route('site.borrower.payments.status', $payment))
            ->assertOk()
            ->assertJsonPath('state', 'failed');

        $this->assertSame('active', $redemption->fresh()->status);
        $this->assertNull($redemption->fresh()->used_at);
    }

    public function test_duplicate_settle_consumes_reward_and_application_points_once(): void
    {
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-IDEM1', 'loyalty_points' => 0]);
        $invitee = $this->makeCustomer([
            'referred_by_customer_id' => $referrer->id,
            'loyalty_points' => 200,
        ]);
        $redemption = app(LoyaltyRedemptionService::class)->redeem($invitee->fresh(), 'application_fee_10');

        $quote = app(PaymentGateService::class)->quote($invitee->fresh(), 10000, 'application_fee', false, null, null, true);
        $this->assertSame($redemption->id, $quote['loyalty_redemption_id']);

        $gate = app(PaymentGateService::class);
        $gate->settle($invitee->fresh(), $quote, 'application_fee', CustomerPayment::class, 1);
        $gate->settle($invitee->fresh(), $quote, 'application_fee', CustomerPayment::class, 1);

        $this->assertSame('used', $redemption->fresh()->status);
        $this->assertSame(1, LoyaltyRedemption::query()->where('customer_id', $invitee->id)->where('status', 'used')->count());
        $this->assertSame(25, app(LoyaltyPointsService::class)->balance($referrer->fresh()));
    }

    public function test_plus_affiliate_quote_uses_discounted_amount_for_commission(): void
    {
        $affiliate = Vendor::create([
            'partner_number' => 'QA-PLUS-AFF',
            'name' => 'Plus Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'affiliate_code' => 'PLUSAFF10',
            'affiliate_kyc_status' => 'verified',
            'affiliate_lifecycle_status' => 'active',
            'affiliate_commission_percent' => 10,
            'metadata' => ['plus_discount_percent' => 10],
        ]);
        $customer = $this->makeCustomer(['affiliate_vendor_id' => $affiliate->id]);

        $quote = app(PaymentGateService::class)->quote($customer->fresh(), 35000, 'kopafasta_plus');

        $this->assertTrue($quote['has_affiliate']);
        $this->assertSame(35000.0, (float) $quote['base']);
        $this->assertSame(3500.0, (float) $quote['affiliate_discount']);
        $this->assertSame(31500.0, (float) $quote['cash_due']);
        $this->assertSame(3150.0, (float) $quote['commission']);
    }

    public function test_points_reverse_is_idempotent(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 0]);
        app(LoyaltyPointsService::class)->earnCustom($customer, 25, 'refer_application', 'test', Customer::class, 99);
        $this->assertSame(25, app(LoyaltyPointsService::class)->balance($customer->fresh()));

        $first = app(GrowthPointsService::class)->reverseUnusedCredits($customer->fresh(), 'refer_application', Customer::class, 99);
        $second = app(GrowthPointsService::class)->reverseUnusedCredits($customer->fresh(), 'refer_application', Customer::class, 99);

        $this->assertSame(25, $first);
        $this->assertSame(0, $second);
        $this->assertSame(0, app(LoyaltyPointsService::class)->balance($customer->fresh()));
    }

    public function test_plus_rewards_page_uses_loyalty_catalogue(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 120]);
        app(PlusService::class)->grantComplimentary($customer, 'QA plus rewards page', null, 30);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.plus.rewards'))
            ->assertOk()
            ->assertSee(__('borrower.rewards.tab_ready'), false)
            ->assertSee(__('borrower.rewards.redeem_button'), false)
            ->assertSee(__('borrower.rewards.plus_badge'), false);
    }

    public function test_share_message_follows_swahili(): void
    {
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-SW1']);
        $message = app(ReferralService::class)->shareMessage($referrer, 'sw');

        $this->assertStringContainsString('pointi', strtolower($message));
        $this->assertStringNotContainsString('ada ya uanachama', strtolower($message));
    }
}
