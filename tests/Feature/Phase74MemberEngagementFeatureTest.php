<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\LoyaltyPointsService;
use App\Services\MemberEngagementService;
use App\Services\NotificationCenterService;
use App\Services\PinService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase74MemberEngagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create(array_merge([
            'user_id'         => $user->id,
            'customer_number' => 'C-ENG'.random_int(100000, 999999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Engage',
            'last_name'       => 'Member',
            'phone'           => '+255700'.random_int(100000, 999999),
        ], $overrides));
    }

    private function makeProduct(array $overrides = []): LoanProduct
    {
        return LoanProduct::create(array_merge([
            'code'                  => 'PL'.random_int(100, 999),
            'name'                  => 'Personal Loan',
            'category'              => 'personal',
            'is_active'             => true,
            'interest_rate'         => 0.03,
            'min_amount'            => 100_000,
            'max_amount'            => 5_000_000,
            'tenure_min_months'     => 1,
            'tenure_max_months'     => 12,
            'application_fee_amount'=> 10_000,
        ], $overrides));
    }

    public function test_successful_referral_counts_registered_invitees(): void
    {
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-ENG001']);
        $this->makeCustomer(['referred_by_customer_id' => $referrer->id]);
        $this->makeCustomer([
            'referred_by_customer_id' => $referrer->id,
            'membership_issued_at'  => now(),
        ]);

        $count = app(ReferralService::class)->successfulReferralCount($referrer);

        $this->assertSame(2, $count);
    }

    public function test_loyalty_points_earn_and_balance(): void
    {
        $customer = $this->makeCustomer();
        $service = app(LoyaltyPointsService::class);

        $earned = $service->earn($customer, 'complete_profile');
        $customer->refresh();

        $this->assertSame(10, $earned);
        $this->assertSame(10, $service->balance($customer));
        $this->assertSame(0, $service->earn($customer, 'complete_profile'));
    }

    public function test_notifications_grouped_by_date(): void
    {
        $customer = $this->makeCustomer();

        NotificationLog::create([
            'customer_id' => $customer->id,
            'channel'     => 'in_app',
            'recipient'   => '/borrower/profile',
            'template'    => 'test',
            'message'     => "Today alert\nDetails",
            'category'    => 'application',
            'status'      => 'sent',
            'created_at'  => now(),
        ]);

        NotificationLog::create([
            'customer_id' => $customer->id,
            'channel'     => 'in_app',
            'recipient'   => '/borrower/profile',
            'template'    => 'test',
            'message'     => "Yesterday alert\nDetails",
            'category'    => 'payment',
            'status'      => 'sent',
            'created_at'  => now()->subDay(),
        ]);

        $groups = app(NotificationCenterService::class)->groupedForCustomer($customer);

        $this->assertCount(1, $groups['today']);
        $this->assertCount(1, $groups['yesterday']);
    }

    public function test_trust_score_and_referral_level_summary(): void
    {
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-ENG002', 'membership_issued_at' => now()]);
        $this->makeCustomer([
            'referred_by_customer_id' => $referrer->id,
            'membership_issued_at'    => now(),
        ]);

        $summary = app(MemberEngagementService::class)->summary($referrer);

        $this->assertArrayHasKey('trust_score', $summary);
        $this->assertSame('bronze', $summary['referral']['level']['key']);
    }

    public function test_underwriting_boosts_increase_limit_for_silver_referrer(): void
    {
        $referrer = $this->makeCustomer([
            'referral_code'         => 'KPF-GLD001',
            'membership_issued_at'  => now(),
            'membership_expires_at' => now()->addYear(),
            'monthly_income'        => 500_000,
            'income_range'          => null,
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->makeCustomer([
                'referred_by_customer_id' => $referrer->id,
                'membership_issued_at'    => now(),
            ]);
        }

        $qualification = app(\App\Services\LoanQualificationService::class)->calculate($referrer);

        $this->assertGreaterThan(1_000_000, (int) $qualification['amount']);
        $this->assertGreaterThanOrEqual(1, (int) ($qualification['boosts']['processing_priority'] ?? 0));
        $this->assertGreaterThan(1.0, (float) ($qualification['boosts']['limit_multiplier'] ?? 1.0));
    }

    public function test_loyalty_points_awarded_on_profile_section_save(): void
    {
        $customer = $this->makeCustomer();

        app(\App\Services\MemberEngagementRewardService::class)->afterProfileSectionSaved($customer, 'residence');

        $this->assertSame(0, (int) $customer->fresh()->loyalty_points);
    }

    public function test_borrower_referrals_page_renders_rewards_card(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $customer = $this->makeCustomer(['user_id' => $user->id, 'referral_code' => 'KPF-ENG003']);

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('site.borrower.referrals'))
            ->assertOk()
            ->assertSee('KPF-ENG003', false);
    }

    public function test_loyalty_redemption_deducts_points_and_creates_active_reward(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 1000]);
        app(LoyaltyPointsService::class)->earn($customer, 'complete_profile');
        $customer->refresh();

        $redemption = app(\App\Services\LoyaltyRedemptionService::class)->redeem($customer->fresh(), 'application_fee_10');

        $this->assertSame('active', $redemption->status);
        $this->assertSame(100, (int) $redemption->points_spent);
        $this->assertLessThan(1000, app(LoyaltyPointsService::class)->balance($customer->fresh()));
    }

    public function test_loyalty_discount_is_not_auto_applied_to_quoted_fee(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 2000]);
        app(\App\Services\LoyaltyRedemptionService::class)->redeem($customer->fresh(), 'application_fee_10');

        $product = $this->makeProduct(['application_fee_amount' => 10_000]);

        $quoted = quoted_application_fee($customer->fresh(), $product);
        $this->assertSame(10000, $quoted);

        $applied = app(\App\Services\PaymentGateService::class)->quote(
            $customer->fresh(),
            10000,
            'application_fee',
            false,
            null,
            null,
            true,
        );
        $this->assertSame(1000.0, $applied['loyalty_discount']);
        $this->assertSame(9000.0, $applied['cash_due']);
    }

    public function test_repayment_preview_includes_engagement_payload(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $customer = $this->makeCustomer([
            'user_id'                => $user->id,
            'membership_issued_at'   => now(),
            'membership_expires_at'  => now()->addYear(),
            'monthly_income'         => 500_000,
        ]);

        $product = $this->makeProduct();

        $this->actingAs($user)
            ->getJson(route('site.borrower.apply.repayment-preview', [
                'loan_product_id' => $product->id,
                'requested_amount' => 500_000,
                'requested_tenure_months' => 6,
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['engagement' => ['limit_amount', 'processing_sla', 'factors']]);
    }

    public function test_borrower_rewards_page_renders(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $this->makeCustomer(['user_id' => $user->id, 'loyalty_points' => 250]);

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('site.borrower.rewards'))
            ->assertOk()
            ->assertSee(__('borrower.rewards.redeem_title'), false);
    }

    public function test_document_points_are_awarded_once_even_after_delete_and_reupload(): void
    {
        $customer = $this->makeCustomer();
        $rewards = app(\App\Services\MemberEngagementRewardService::class);
        $loyalty = app(LoyaltyPointsService::class);

        $rewards->afterDocumentUploaded($customer, 'bank_statement');
        $this->assertSame(0, $loyalty->balance($customer->fresh()));

        $rewards->afterDocumentUploaded($customer, 'bank_statement');
        $this->assertSame(0, $loyalty->balance($customer->fresh()));

        $rewards->afterProfileSectionSaved($customer, 'activity');
        $first = $loyalty->balance($customer->fresh());
        $rewards->afterProfileSectionSaved($customer, 'activity');
        $this->assertSame($first, $loyalty->balance($customer->fresh()));
    }
}
