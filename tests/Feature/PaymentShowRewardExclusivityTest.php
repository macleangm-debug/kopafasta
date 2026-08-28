<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyRedemption;
use App\Models\User;
use App\Services\LoyaltyRedemptionService;
use App\Services\PaymentGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentShowRewardExclusivityTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create(array_merge([
            'user_id'         => $user->id,
            'customer_number' => 'C-RWD'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Reward',
            'last_name'       => 'Member',
            'phone'           => '+255700'.random_int(100000, 999999),
            'loyalty_points'  => 500,
        ], $overrides));
    }

    public function test_unlocked_reward_is_not_applied_until_requested(): void
    {
        $customer = $this->makeCustomer();
        app(LoyaltyRedemptionService::class)->redeem($customer->fresh(), 'application_fee_10');

        $idle = app(PaymentGateService::class)->quote($customer->fresh(), 10000, 'application_fee');
        $this->assertSame(0.0, $idle['loyalty_discount']);
        $this->assertSame(10000.0, $idle['cash_due']);

        $applied = app(PaymentGateService::class)->quote($customer->fresh(), 10000, 'application_fee', false, null, null, true);
        $this->assertSame(1000.0, $applied['loyalty_discount']);
        $this->assertSame(9000.0, $applied['cash_due']);
        $this->assertSame(10000.0, $applied['base']);
    }

    public function test_reward_and_promo_do_not_stack_by_default(): void
    {
        $customer = $this->makeCustomer();
        app(LoyaltyRedemptionService::class)->redeem($customer->fresh(), 'application_fee_10');

        $promo = \App\Models\Promotion::query()->create([
            'name' => 'KOPA10',
            'code' => 'KOPA10',
            'type' => 'promo_code',
            'status' => 'active',
            'discount_percent' => 10,
            'applies_to' => 'application_fee',
        ]);

        $quote = app(PaymentGateService::class)->quote(
            $customer->fresh(),
            10000,
            'application_fee',
            false,
            'KOPA10',
            null,
            true,
        );

        $this->assertTrue($quote['loyalty_discount'] === 0.0 || $quote['promo_discount'] === 0.0);
        $this->assertLessThanOrEqual(1000.0, (float) $quote['total_discount']);
        $this->assertSame(10000.0, $quote['base']);
        $this->assertTrue($promo->exists);
    }

    public function test_fee_waiver_covers_the_obligation(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 800]);
        app(LoyaltyRedemptionService::class)->redeem($customer->fresh(), 'application_fee_waive');

        $quote = app(PaymentGateService::class)->quote($customer->fresh(), 10000, 'application_fee', false, null, null, true);
        $this->assertEquals(10000.0, (float) $quote['loyalty_discount']);
        $this->assertEquals(0.0, (float) $quote['cash_due']);
    }

    public function test_plus_only_reward_rejects_non_plus_member(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 500]);

        $this->expectException(\InvalidArgumentException::class);
        app(LoyaltyRedemptionService::class)->redeem($customer, 'plus_application_fee_waive');
    }

    public function test_public_rewards_page_explains_qualifying_activities(): void
    {
        $content = $this->get(route('site.rewards'))->assertOk()->getContent();

        $this->assertTrue(
            str_contains($content, 'Not every activity earns points')
            || str_contains($content, 'Si kila shughuli inatoa pointi')
        );
        $this->assertTrue(
            str_contains($content, '10% off application fee')
            || str_contains($content, 'Punguzo la 10% kwa ada ya maombi')
        );
        $this->assertStringContainsString('+25', $content);
    }
}
