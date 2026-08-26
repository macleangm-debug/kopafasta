<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\MemberEngagementService;
use App\Services\PaymentGateService;
use App\Services\PinService;
use App\Services\RepaymentStreakRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepaymentStreakRewardTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create(array_merge([
            'user_id'         => $user->id,
            'customer_number' => 'C-STR'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Streak',
            'last_name'       => 'Borrower',
            'phone'           => '+255700'.random_int(100000, 999999),
        ], $overrides));
    }

    public function test_streak_rewards_are_points_not_fee_discounts(): void
    {
        $customer = $this->makeCustomer();
        $this->mock(MemberEngagementService::class, function ($mock) {
            $mock->shouldReceive('repaymentStreak')->andReturn(['count' => 12]);
        });

        $result = app(RepaymentStreakRewardService::class)->discountForFee($customer, 'application_fee', 10_000);

        $this->assertSame(0.0, $result['percent']);
        $this->assertSame(0.0, $result['discount']);
    }

    public function test_payment_gate_quote_does_not_apply_a_streak_fee_discount(): void
    {
        $customer = $this->makeCustomer();
        $this->mock(MemberEngagementService::class, function ($mock) {
            $mock->shouldReceive('repaymentStreak')->andReturn(['count' => 5]);
        });

        $quote = app(PaymentGateService::class)->quote($customer, 10_000, 'application_fee', useWallet: true);

        $this->assertSame(0.0, $quote['streak_discount']);
        $this->assertArrayHasKey('cash_due', $quote);
    }

    public function test_engagement_hub_renders(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $this->makeCustomer(['user_id' => $user->id, 'referral_code' => 'KPF-HUB001', 'loyalty_points' => 100]);

        $this->actingAs($user)
            ->get(route('site.borrower.engagement'))
            ->assertOk()
            ->assertSee('KPF-HUB001', false)
            ->assertSee(__('borrower.engagement.tabs_short.overview'), false)
            ->assertSee(__('borrower.referrals.rewards_title'), false)
            ->assertSee(__('borrower.rewards.balance'), false);
    }

    public function test_legacy_referrals_route_redirects_to_engagement_tab(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $this->makeCustomer(['user_id' => $user->id, 'referral_code' => 'KPF-RED001']);

        $this->actingAs($user)
            ->get(route('site.borrower.referrals'))
            ->assertRedirect(route('site.borrower.engagement', ['tab' => 'referrals']));
    }

    public function test_wallet_balance_converts_to_points(): void
    {
        $this->assertSame(5000, wallet_balance_as_points(5000));
        $this->assertSame(2500, wallet_balance_as_points(2500));
        $this->assertSame(0, wallet_balance_as_points(0));
    }
}
