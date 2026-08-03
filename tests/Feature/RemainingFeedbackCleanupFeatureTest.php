<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanFee;
use App\Models\LoanProduct;
use App\Models\Repayment;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\LoyaltyPointsService;
use App\Services\MemberEngagementRewardService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemainingFeedbackCleanupFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create(array_merge([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-REM-'.random_int(100, 999),
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Remain',
            'last_name'             => 'Ing',
            'phone'                 => '+255700'.random_int(100000, 999999),
            'membership_status'     => 'active',
            'membership_issued_at'  => now(),
            'membership_expires_at' => now()->addYear(),
            'loyalty_points'        => 200,
        ], $overrides));
    }

    public function test_membership_nav_removed_and_legacy_route_redirects_to_profile(): void
    {
        $customer = $this->makeCustomer();

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->getContent();

        // Standalone membership nav item removed; membership lives under profile only.
        $this->assertStringNotContainsString('href="'.route('site.membership.show').'"', $html);

        $this->actingAs($customer->user)
            ->get(route('site.membership.show'))
            ->assertRedirect(route('site.borrower.profile', ['section' => 'membership']));
    }

    public function test_kin_section_redirects_to_personal_with_focus(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'kin']))
            ->assertRedirect(route('site.borrower.profile', [
                'section' => 'personal',
                'focus'   => 'kin',
            ]));
    }

    public function test_quote_step_gates_rewards_to_redeemable_only(): void
    {
        $blade = file_get_contents(resource_path('views/site/apply/_quote-step.blade.php'));

        $this->assertNotFalse($blade);
        $this->assertStringContainsString('canShowQuoteRewards()', $blade);
        $this->assertStringNotContainsString('rate_disclosure', $blade);
        $this->assertStringContainsString('formatAmount(quote.interest)', $blade);
        $this->assertSame('Interest estimate (TZS)', __('borrower.apply.quote.interest_est_tzs'));
    }

    public function test_late_repayment_deducts_points_once(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 200]);
        $product = LoanProduct::create([
            'code'              => 'PL-REM',
            'name'              => 'Remain Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_number'         => 'LN-REM-1',
            'principal_amount'    => 500_000,
            'approved_amount'     => 500_000,
            'outstanding_balance' => 400_000,
            'interest_rate'       => 0.15,
            'tenure_months'       => 12,
            'status'              => 'active',
        ]);
        $schedule = RepaymentSchedule::create([
            'loan_id'        => $loan->id,
            'installment_no' => 1,
            'due_date'       => now()->subDays(5)->toDateString(),
            'principal_due'  => 40_000,
            'interest_due'   => 5_000,
            'total_due'      => 45_000,
            'amount_paid'    => 45_000,
            'status'         => 'paid',
            'paid_at'        => now()->subDay(),
        ]);
        $repayment = Repayment::create([
            'loan_id'     => $loan->id,
            'reference'   => 'RCP-REM-1',
            'amount'      => 45_000,
            'status'      => 'approved',
            'paid_at'     => now()->subDay(),
            'channel'     => 'mobile_money',
            'recorded_by' => $customer->user_id,
        ]);

        $rewards = app(MemberEngagementRewardService::class);
        $rewards->afterRepaymentSchedulePaid($schedule, $repayment);
        $rewards->afterRepaymentSchedulePaid($schedule, $repayment);

        $this->assertSame(150, app(LoyaltyPointsService::class)->balance($customer->fresh()));
        $this->assertDatabaseHas('loyalty_point_transactions', [
            'customer_id'    => $customer->id,
            'type'           => 'debit',
            'action_key'     => 'late_repayment',
            'reference_type' => RepaymentSchedule::class,
            'reference_id'   => $schedule->id,
            'points'         => -50,
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'customer_id' => $customer->id,
            'template'     => 'loyalty_points_deducted',
            'category'     => 'promotions',
        ]);
    }

    public function test_late_fee_accrual_deducts_points_once_per_fee(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 80]);
        $product = LoanProduct::create([
            'code'              => 'PL-REM2',
            'name'              => 'Remain Fee Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_number'         => 'LN-REM-2',
            'principal_amount'    => 500_000,
            'approved_amount'     => 500_000,
            'outstanding_balance' => 400_000,
            'interest_rate'       => 0.15,
            'tenure_months'       => 12,
            'status'              => 'active',
        ]);
        $fee = LoanFee::create([
            'loan_id'         => $loan->id,
            'code'            => 'LATE_FEE',
            'name'            => 'Late payment fee',
            'type'            => 'fixed',
            'basis'           => 'overdue_balance',
            'rate_or_amount'  => 5_000,
            'computed_amount' => 5_000,
            'status'          => 'charged',
            'charge_when'     => 'late',
            'notes'           => 'accrual:'.now()->toDateString(),
        ]);

        $rewards = app(MemberEngagementRewardService::class);
        $rewards->afterLateFeeAccrued($customer, $fee);
        $rewards->afterLateFeeAccrued($customer, $fee);

        $this->assertSame(55, app(LoyaltyPointsService::class)->balance($customer->fresh()));
        $this->assertDatabaseCount('loyalty_point_transactions', 1);
    }

    public function test_penalty_never_takes_balance_below_zero(): void
    {
        $customer = $this->makeCustomer(['loyalty_points' => 10]);

        $deducted = app(LoyaltyPointsService::class)->deductPenalty(
            $customer,
            'late_repayment',
            null,
            'test',
            1,
        );

        $this->assertSame(10, $deducted);
        $this->assertSame(0, app(LoyaltyPointsService::class)->balance($customer->fresh()));
    }

    public function test_admin_can_save_loyalty_penalties(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $actions = config('gamification.loyalty_points.actions');

        $payload = [
            'actions' => collect($actions)->mapWithKeys(fn ($action, $key) => [
                $key => [
                    'label'  => $action['label'],
                    'points' => $action['points'],
                ],
            ])->all(),
            'penalties' => [
                'late_repayment' => [
                    'label'   => 'Late repayment custom',
                    'points'  => 40,
                    'enabled' => '1',
                ],
                'late_fee_accrual' => [
                    'label'   => 'Late fee custom',
                    'points'  => 15,
                    'enabled' => '0',
                ],
            ],
        ];

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.engagement.loyalty-points.save'), $payload)
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.engagement.loyalty-points'))
            ->assertOk()
            ->assertSee('Penalty deductions', false)
            ->assertSee('Late repayment custom', false)
            ->assertSee('value="40"', false);

        $customer = $this->makeCustomer(['loyalty_points' => 100]);
        $deducted = app(LoyaltyPointsService::class)->deductPenalty($customer, 'late_fee_accrual', null, 'fee', 9);
        $this->assertSame(0, $deducted);

        $deductedLate = app(LoyaltyPointsService::class)->deductPenalty($customer, 'late_repayment', null, 'sched', 9);
        $this->assertSame(40, $deductedLate);
    }
}
