<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\LoyaltyPointsService;
use App\Services\LoyaltyRedemptionService;
use App\Services\PinService;
use App\Support\Celebration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanHeroAndRewardsUxFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create(array_merge([
            'user_id'               => $user->id,
            'customer_number'       => 'C-HR'.random_int(100, 999),
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Hero',
            'last_name'             => 'Rewards',
            'phone'                 => '+255700'.random_int(100000, 999999),
            'membership_issued_at'  => now(),
            'membership_expires_at' => now()->addYear(),
            'loyalty_points'        => 2000,
        ], $overrides));
    }

    public function test_product_features_omit_guarantor_required(): void
    {
        $product = LoanProduct::create([
            'code'               => 'PL'.random_int(100, 999),
            'name'               => 'Personal Loan',
            'category'           => 'personal',
            'is_active'          => true,
            'interest_rate'      => 0.03,
            'min_amount'         => 100_000,
            'max_amount'         => 5_000_000,
            'tenure_min_months'  => 1,
            'tenure_max_months'  => 12,
            'requires_guarantor' => true,
        ]);

        $features = loan_product_features($product);

        $this->assertFalse(collect($features)->contains(
            fn ($f) => str_contains(strtolower((string) $f), 'guarantor')
        ));
    }

    public function test_earning_points_notifies_and_celebrates(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 0]);

        $earned = app(LoyaltyPointsService::class)->earn($customer, 'complete_profile');

        $this->assertSame(10, $earned);
        $this->assertContains('points_earned', Celebration::reasons());
        $this->assertDatabaseHas('notification_logs', [
            'customer_id' => $customer->id,
            'template'     => 'loyalty_points_earned',
            'category'     => 'promotions',
        ]);
    }

    public function test_fee_reward_redeem_redirects_to_loan_products(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 2000]);
        app(PinService::class)->setPin($customer->user, '1234');

        $this->actingAs($customer->user)
            ->post(route('site.borrower.engagement.redeem'), [
                'option_key' => 'application_fee_10',
            ])
            ->assertRedirect(route('site.borrower.loan-products'));

        $this->assertTrue(
            app(LoyaltyRedemptionService::class)->activeRewards($customer->fresh())
                ->contains(fn ($r) => $r->option_key === 'application_fee_10')
        );
    }
}
