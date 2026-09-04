<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanProduct;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CustomerPaymentService;
use App\Services\GrowthPointsService;
use App\Services\LoyaltyRedemptionService;
use App\Services\PaymentGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentShowAdjustmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')]);

        return Customer::create(array_merge([
            'user_id' => $user->id,
            'customer_number' => 'CU-ADJ-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Adjust',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
            'loyalty_points' => 500,
        ], $overrides));
    }

    private function feePayment(Customer $customer, int $amount = 10_000): CustomerPayment
    {
        $product = LoanProduct::create([
            'code' => 'IL-ADJ-'.random_int(100, 999),
            'name' => 'Individual Loan',
            'category' => 'individual',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'application_fee_amount' => $amount,
        ]);

        return CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => $amount,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'reference' => 'PAY-ADJ-'.random_int(1000, 9999),
            'provider_meta' => [
                'apply_context' => [
                    'loan_product_id' => $product->id,
                    'gross_amount' => $amount,
                    'back_url' => route('site.borrower.apply', ['product' => $product->id, 'resume' => 1, 'step_key' => 'quote']),
                ],
                'pricing' => ['gross' => $amount],
            ],
        ]);
    }

    private function affiliate(string $code = 'KITONGA'): Vendor
    {
        return Vendor::create([
            'vendor_number' => 'AFF-'.$code.'-'.random_int(10, 99),
            'name' => 'Kitonga Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'affiliate_code' => $code,
            'affiliate_kyc_status' => 'verified',
            'affiliate_lifecycle_status' => 'active',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
            'application_discount_percent' => 10,
            'registration_discount_percent' => 10,
        ]);
    }

    public function test_kitonga_apply_returns_discount_breakdown_or_a_clear_error(): void
    {
        $customer = $this->borrower();
        $this->affiliate('KITONGA');
        $payment = $this->feePayment($customer);

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.payments.adjust', $payment), [
                'promo_code' => 'KITONGA',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('promo_valid', true)
            ->assertJsonPath('quote.affiliate_discount', 1000)
            ->assertJsonPath('quote.cash_due', 9000)
            ->assertJsonPath('quote.base', 10000);

        $quote = app(PaymentGateService::class)->quote($customer->fresh(), 10000, 'application_fee', false, 'KITONGA');
        $keys = collect($quote['lines'])->pluck('key')->all();
        $this->assertContains('base', $keys);
        $this->assertContains('affiliate', $keys);
        $this->assertContains('payable', $keys);
    }

    public function test_invalid_promo_returns_an_inline_error(): void
    {
        $customer = $this->borrower();
        $payment = $this->feePayment($customer);

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.payments.adjust', $payment), [
                'promo_code' => 'NOT-A-CODE',
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('promo_valid', false)
            ->assertJsonPath('quote.cash_due', 10000);
    }

    public function test_payment_show_reads_promo_query_and_shows_two_step_card(): void
    {
        $customer = $this->borrower();
        $this->affiliate('KITONGA');
        $payment = $this->feePayment($customer);

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.payments.show', $payment).'?promo_code=KITONGA')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('checkoutStep', $html);
        $this->assertStringContainsString(__('borrower.payments_page.show.amount_to_pay'), $html);
        $this->assertStringContainsString('KITONGA', $html);
        $this->assertStringContainsString(__('borrower.membership.pay_now'), $html);
        $this->assertStringContainsString(__('borrower.apply.next'), $html);
    }

    public function test_affordable_reward_is_visible_and_insufficient_points_are_hidden(): void
    {
        $rich = $this->borrower(['loyalty_points' => 500]);
        $poor = $this->borrower(['loyalty_points' => 1]);
        $loyalty = app(LoyaltyRedemptionService::class);

        $this->assertNotNull($loyalty->checkoutRewardForFee($rich, 'application_fee', 10000));
        $this->assertNull($loyalty->checkoutRewardForFee($poor, 'application_fee', 10000));
    }

    public function test_reward_points_are_not_consumed_until_settle(): void
    {
        $customer = $this->borrower(['loyalty_points' => 500]);
        $before = (int) $customer->fresh()->loyalty_points;
        $preview = app(LoyaltyRedemptionService::class)->previewDiscountForFee($customer, 'application_fee', 10000);
        $this->assertGreaterThan(0, $preview['discount']);
        $this->assertSame($before, (int) $customer->fresh()->loyalty_points);

        app(PaymentGateService::class)->quote($customer->fresh(), 10000, 'application_fee', false, null, null, true);
        $this->assertSame($before, (int) $customer->fresh()->loyalty_points);
    }

    public function test_payment_show_persists_auto_attributed_affiliate_breakdown(): void
    {
        $customer = $this->borrower();
        $affiliate = $this->affiliate('KITONGA');
        $customer->update(['affiliate_vendor_id' => $affiliate->id]);
        $payment = $this->feePayment($customer);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertSee('KITONGA', false)
            ->assertSee(__('borrower.payments_page.show.amount_to_pay'), false);

        $payment->refresh();
        $this->assertEquals(10000, (float) data_get($payment->provider_meta, 'pricing.gross'));
        $this->assertEquals(1000, (float) data_get($payment->provider_meta, 'pricing.affiliate_discount'));
        $this->assertEquals(9000, (float) data_get($payment->provider_meta, 'pricing.net_payable'));
        $this->assertEquals(9000, CustomerPaymentService::collectableAmount($payment));
    }

    public function test_inactive_affiliate_code_is_rejected_with_a_message(): void
    {
        $customer = $this->borrower();
        $this->affiliate('KITONGA')->update(['status' => 'inactive']);
        $payment = $this->feePayment($customer);

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.payments.adjust', $payment), [
                'promo_code' => 'KITONGA',
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('promo_valid', false)
            ->assertJsonFragment(['message' => __('borrower.membership.promo_invalid')]);
    }

    public function test_reward_and_promo_do_not_stack_by_default(): void
    {
        $this->assertFalse(app(GrowthPointsService::class)->allowRewardAndPromo());
        $this->assertFalse((bool) config('gamification.loyalty_points.stack_with_promo', false));
    }
}
