<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationRequirementsService;
use App\Services\Grades\CustomerGradeEngine;
use App\Services\Plus\PlusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerGradeAndPlusFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create(array_merge([
            'user_id' => $user->id,
            'customer_number' => 'CU-GRD-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Mushi',
            'phone' => '25571234'.random_int(1000, 9999),
            'country_code' => 'TZ',
        ], $overrides));
    }

    public function test_unpaid_customer_is_not_blocked_by_membership_fee(): void
    {
        $customer = $this->customer();
        $checklist = app(ApplicationRequirementsService::class)->checklist($customer);

        $this->assertNull(collect($checklist['items'])->firstWhere('key', 'membership'));
        $this->assertNull(collect($checklist['items'])->firstWhere('key', 'registration_fee'));
    }

    public function test_new_customer_evaluates_to_bronze(): void
    {
        $customer = $this->customer();
        $evaluation = app(CustomerGradeEngine::class)->evaluate($customer, 'test');

        $this->assertSame('bronze', $evaluation->effective_grade);
        $this->assertSame('bronze', $customer->fresh()->grade);
        $this->assertArrayNotHasKey('plus_subscriber', $evaluation->facts ?? []);
    }

    public function test_score_and_gates_can_reach_gold_from_source_of_truth_facts(): void
    {
        $facts = [
            'effective_on_time_ratio' => 96,
            'recent_on_time_ratio' => 98,
            'lifetime_principal_borrowed' => 6_000_000,
            'lifetime_amount_repaid' => 7_000_000,
            'qualifying_completed_facilities' => 3,
            'relationship_days' => 400,
            'active_relationship_months' => 14,
            'verified_profile_score' => 100,
            'current_days_past_due' => 0,
            'open_overdue_count' => 0,
            'defaulted_facilities_count' => 0,
            'max_days_past_due_recent' => 0,
            'restructured_facilities_count' => 0,
            'concurrent_facility_count' => 1,
            'facilities_opened_recently' => 0,
            'tiny_completed_facilities' => 0,
            'rapid_cycle_count' => 0,
            'reversed_payments_count' => 0,
        ];

        $preview = app(CustomerGradeEngine::class)->preview($facts, 'TZ');

        $this->assertGreaterThanOrEqual(60, $preview['score']);
        $this->assertContains($preview['gateGrade'], ['gold', 'platinum']);
    }

    public function test_tiny_loans_fail_gold_gate(): void
    {
        $facts = [
            'effective_on_time_ratio' => 100,
            'lifetime_principal_borrowed' => 80_000,
            'lifetime_amount_repaid' => 80_000,
            'qualifying_completed_facilities' => 0,
            'relationship_days' => 400,
            'current_days_past_due' => 0,
            'open_overdue_count' => 0,
            'defaulted_facilities_count' => 0,
            'verified_profile_score' => 100,
        ];

        $preview = app(CustomerGradeEngine::class)->preview($facts, 'TZ');

        $this->assertSame('bronze', $preview['gateGrade']);
    }

    public function test_plus_activation_does_not_change_grade_or_trust_inputs(): void
    {
        $customer = $this->customer();
        $engine = app(CustomerGradeEngine::class);
        $before = $engine->evaluate($customer, 'before_plus');

        app(PlusService::class)->activate($customer, [
            'payment_reference' => 'PLUS-TEST-1',
            'price_paid' => 3000,
        ]);

        $after = $engine->evaluate($customer->fresh(), 'after_plus');

        $this->assertTrue(app(PlusService::class)->isActive($customer->fresh()));
        $this->assertSame($before->score, $after->score);
        $this->assertSame($before->effective_grade, $after->effective_grade);
        $this->assertArrayNotHasKey('plus_subscriber', $after->facts ?? []);
        $this->assertArrayNotHasKey('kopafasta_plus', $after->facts ?? []);
    }

    public function test_downgrade_enters_review_instead_of_immediate_drop(): void
    {
        $customer = $this->customer(['grade' => 'gold', 'calculated_grade' => 'gold', 'grade_score' => 70]);
        $evaluation = app(CustomerGradeEngine::class)->evaluate($customer, 'deterioration');

        $this->assertSame('gold', $evaluation->effective_grade);
        $this->assertSame('under_review', $evaluation->grade_status);
        $this->assertSame('bronze', $evaluation->calculated_grade);
    }

    public function test_rewards_reject_borrowing_incentives(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(PlusService::class)->awardReward($this->customer(), 'loan', 50, 'Borrowed a loan', 'borrow');
    }

    public function test_plus_with_poor_credit_cannot_reach_gold(): void
    {
        $customer = $this->customer();
        app(PlusService::class)->activate($customer, ['payment_reference' => 'PLUS-POOR', 'price_paid' => 3000]);
        $preview = app(CustomerGradeEngine::class)->preview([
            'effective_on_time_ratio' => 40,
            'qualifying_completed_facilities' => 0,
            'relationship_days' => 10,
            'current_days_past_due' => 21,
            'open_overdue_count' => 2,
            'defaulted_facilities_count' => 1,
        ], 'TZ');

        $this->assertTrue(app(PlusService::class)->isActive($customer->fresh()));
        $this->assertSame('bronze', $preview['gateGrade']);
    }

    public function test_excellent_non_plus_customer_can_reach_platinum_band(): void
    {
        $preview = app(CustomerGradeEngine::class)->preview([
            'effective_on_time_ratio' => 98,
            'recent_on_time_ratio' => 99,
            'lifetime_principal_borrowed' => 20_000_000,
            'lifetime_amount_repaid' => 22_000_000,
            'qualifying_completed_facilities' => 6,
            'relationship_days' => 800,
            'active_relationship_months' => 28,
            'verified_profile_score' => 100,
            'current_days_past_due' => 0,
            'open_overdue_count' => 0,
            'defaulted_facilities_count' => 0,
            'max_days_past_due_recent' => 0,
            'restructured_facilities_count' => 0,
            'concurrent_facility_count' => 1,
            'facilities_opened_recently' => 0,
            'tiny_completed_facilities' => 0,
            'rapid_cycle_count' => 0,
            'reversed_payments_count' => 0,
        ], 'TZ');

        $this->assertGreaterThanOrEqual(80, $preview['score']);
        $this->assertContains($preview['gateGrade'], ['gold', 'platinum']);
    }

    public function test_product_grade_eligibility_hides_higher_grade_products(): void
    {
        $customer = $this->customer(['grade' => 'bronze']);
        $product = new \App\Models\LoanProduct(['eligible_grades' => ['gold', 'platinum']]);

        $this->assertFalse(app(\App\Services\Grades\GradeBenefitService::class)->productEligible($customer, $product));
    }

    public function test_staff_override_requires_reason_and_expiry(): void
    {
        $customer = $this->customer();
        $evaluation = app(CustomerGradeEngine::class)->staffOverride(
            $customer,
            'silver',
            'Temporary service recovery after a system error.',
            now()->addDays(14),
            null,
        );

        $this->assertSame('silver', $customer->fresh()->grade_override);
        $this->assertSame('silver', $evaluation->effective_grade);
    }


    public function test_existing_customer_who_never_paid_membership_has_normal_lending_checklist(): void
    {
        $customer = $this->customer(['membership_expires_at' => null, 'last_renewal_at' => null]);
        $checklist = app(\App\Services\ApplicationRequirementsService::class)->checklist($customer);

        $this->assertNull(collect($checklist['items'])->firstWhere('key', 'membership'));
    }

    public function test_plus_rewards_can_be_redeemed_without_borrowing(): void
    {
        $customer = $this->customer();
        $plus = app(\App\Services\Plus\PlusService::class);
        $plus->awardReward($customer, 'lesson', 40, 'Watched monthly lesson', 'plus');
        $plus->redeemReward($customer, 10, 'Airtime bundle');

        $this->assertSame(30, $plus->rewardBalance($customer));
    }

    public function test_rapid_cycle_and_reversal_histories_stay_off_gold(): void
    {
        $engine = app(CustomerGradeEngine::class);

        $rapid = $engine->preview([
            'effective_on_time_ratio' => 100,
            'qualifying_completed_facilities' => 4,
            'relationship_days' => 21,
            'lifetime_principal_borrowed' => 400_000,
            'rapid_cycle_count' => 4,
            'current_days_past_due' => 0,
        ], 'TZ');
        $this->assertNotSame('gold', $rapid['gateGrade']);

        $reversals = $engine->preview([
            'effective_on_time_ratio' => 100,
            'qualifying_completed_facilities' => 5,
            'relationship_days' => 400,
            'reversed_payments_count' => 5,
            'current_days_past_due' => 0,
        ], 'TZ');
        $this->assertContains($reversals['integrity'] ?? 'review', ['watch', 'review', 'restricted']);
    }

    public function test_grade_tenure_cap_and_staff_watch_copy_are_wired(): void
    {
        $bronze = $this->customer(['grade' => 'bronze']);
        $gold = $this->customer(['grade' => 'gold']);
        $benefits = app(\App\Services\Grades\GradeBenefitService::class);

        $this->assertSame(12, $benefits->maxTenureMonths($bronze));
        $this->assertSame(24, $benefits->maxTenureMonths($gold));
        $this->assertNotSame('borrow', strtolower($benefits->servicePriority($gold)));

        $gold->update(['grade_integrity' => 'review']);
        \App\Models\CustomerGradeEvaluation::query()->create([
            'customer_id' => $gold->id,
            'rule_version' => 1,
            'trigger' => 'test',
            'score' => 70,
            'component_scores' => [],
            'calculated_grade' => 'gold',
            'effective_grade' => 'gold',
            'previous_grade' => 'gold',
            'grade_status' => 'ok',
            'integrity_status' => 'review',
            'facts' => ['rapid_cycle_count' => 4],
            'gates_passed' => [],
            'gates_failed' => [],
            'integrity_signals' => ['rapid_facility_cycling'],
            'reason' => 'Rapid facility cycling detected.',
        ]);
        $copy = $benefits->staffIntegrityCopy($gold->fresh());
        $this->assertStringContainsString('Rapid facility cycling', $copy[0]['title']);
    }

    public function test_historically_paid_membership_is_not_a_lending_gate(): void
    {
        $customer = $this->customer([
            'membership_expires_at' => now()->subYear(),
            'last_renewal_at' => now()->subYear(),
        ]);
        $checklist = app(ApplicationRequirementsService::class)->checklist($customer);

        $this->assertNull(collect($checklist['items'])->firstWhere('key', 'membership'));
    }

    public function test_non_member_joins_plus_through_shared_payment_show_and_posts_gl_4080_once(): void
    {
        $this->seed(\Database\Seeders\DefaultChartOfAccountsSeeder::class);
        $this->seed(\Database\Seeders\FinanceDefaultsSeeder::class);

        $customer = $this->customer();
        $user = $customer->user;
        app(\App\Services\PinService::class)->setPin($user, '1234');
        app(\App\Services\PinRecoveryChallengeService::class)->enroll($user, [
            'mother_first_name' => 'Amina',
            'birth_village' => 'Moshi',
            'primary_school' => 'Uhuru',
        ]);

        $price = app(PlusService::class)->priceFor($customer);

        $this->actingAs($user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee(strtoupper((string) ($customer->grade ?: 'bronze')), false)
            ->assertSee(__('plus.card.explore'), false)
            ->assertSee(format_money(500_000), false)
            ->assertSee('kf-premium-panel', false)
            ->assertDontSee(__('borrower.dashboard.hero.under_review_subtitle'), false);

        $response = $this->actingAs($user)
            ->from(route('site.borrower.plus.home'))
            ->post(route('site.borrower.plus.join'));

        $payment = \App\Models\CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('payment_type', 'kopafasta_plus')
            ->latest('id')
            ->first();

        $this->assertNotNull($payment);
        $response->assertRedirect(route('site.borrower.payments.show', $payment));
        $this->assertEquals($price['amount'], (float) $payment->amount);
        $this->assertContains($payment->status, ['awaiting_payment', 'pending_verification', 'processing']);
        $this->assertFalse(app(PlusService::class)->isActive($customer->fresh()));

        $this->actingAs($user)
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk();

        $verified = app(\App\Services\CustomerPaymentService::class)->verify($payment);
        $this->assertTrue($verified->isVerified());
        $this->assertTrue(app(PlusService::class)->isActive($customer->fresh()));

        app(PlusService::class)->activate($customer->fresh(), [
            'payment_reference' => $payment->reference,
            'price_paid' => $payment->amount,
        ]);

        $this->assertSame(1, \App\Models\PlusSubscription::query()->where('customer_id', $customer->id)->count());

        $income = \App\Models\ChartOfAccount::query()->where('code', '4080')->first();
        $this->assertNotNull($income);
        $credits = \App\Models\JournalEntryLine::query()
            ->where('chart_of_account_id', $income->id)
            ->where('credit', '>', 0)
            ->count();
        $this->assertSame(1, $credits);

        $this->assertSame(
            route('site.borrower.plus.welcome'),
            app(\App\Services\CustomerPaymentService::class)->successRedirectUrl($verified)
        );

        \App\Models\PlusLesson::query()->create([
            'month' => now()->format('Y-m'),
            'title_en' => 'Keep a money diary',
            'title_sw' => 'Weka daftari la pesa',
            'published_at' => now(),
            'duration_minutes' => 7,
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.plus.welcome'))
            ->assertOk()
            ->assertSee(__('plus.welcome.title'), false);

        $this->actingAs($user)
            ->get(route('site.borrower.plus.learn'))
            ->assertOk()
            ->assertSee('Weka daftari la pesa', false);

        $this->actingAs($user)
            ->get(route('site.borrower.plus.home'))
            ->assertOk()
            ->assertSee('Kopafasta Plus', false)
            ->assertSee(__('plus.home.learn'), false)
            ->assertSee(__('plus.home.money'), false);

        $this->actingAs($user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee(__('plus.card.plus'), false);
    }

    public function test_complimentary_plus_is_recorded_without_a_payment(): void
    {
        $customer = $this->customer();
        $subscription = app(PlusService::class)->grantComplimentary(
            $customer,
            'Staff recovery after a billing error.',
            1,
            14
        );

        $this->assertTrue(app(PlusService::class)->isActive($customer->fresh()));
        $this->assertTrue($subscription->complimentary);
        $this->assertSame(0.0, (float) $subscription->price_paid);
        $this->assertNotEmpty($subscription->entitlements['complimentary_grants'] ?? []);
        $this->assertSame(0, \App\Models\CustomerPayment::query()->where('customer_id', $customer->id)->count());
    }

    public function test_hero_and_plus_follow_grade_access_and_hide_application_tracking(): void
    {
        $customer = $this->customer([
            'grade' => 'bronze',
            'member_no' => 'KPF-TZ-TEST',
            'country_code' => 'TZ',
        ]);
        $user = $customer->user;
        app(\App\Services\PinService::class)->setPin($user, '1234');
        app(\App\Services\PinRecoveryChallengeService::class)->enroll($user, [
            'mother_first_name' => 'Amina',
            'birth_village' => 'Moshi',
            'primary_school' => 'Uhuru',
        ]);
        $product = LoanProduct::create([
            'code' => 'PL-GRD',
            'name' => 'Grade Test Product',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
        LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GL-HIDE',
            'status' => 'submitted',
            'current_stage' => 'screening',
            'requested_amount' => 5_200_000,
            'requested_tenure_months' => 12,
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee(format_money(500_000), false)
            ->assertSee('KPF-TZ-TEST', false)
            ->assertSee('kf-premium-panel', false)
            ->assertDontSee('APP-GL-HIDE', false)
            ->assertDontSee(__('borrower.dashboard.hero.under_review_title'), false);

        $this->actingAs($user)
            ->get(route('site.borrower.plus.home'))
            ->assertOk()
            ->assertSee(format_money(500_000), false)
            ->assertSee(__('plus.home.explore'), false)
            ->assertSee('kf-premium-panel', false);
    }
}
