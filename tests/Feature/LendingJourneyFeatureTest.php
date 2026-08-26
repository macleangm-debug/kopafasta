<?php

namespace Tests\Feature;

use App\Models\ApprovalLimit;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerDisbursementAccount;
use App\Models\CustomerPayment;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanApplicationPostApprovalFee;
use App\Models\LoanProduct;
use App\Models\Repayment;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\ActiveLoanServicingService;
use App\Services\ApplicationDisbursementReadinessService;
use App\Services\CapitalPartnerAllocationService;
use App\Services\CollateralSecureService;
use App\Services\CreditAuthorityService;
use App\Services\CustomerDisbursementDetailsService;
use App\Services\Grades\GradeBenefitService;
use App\Services\LendingJourneyService;
use App\Services\LoanApplicationWorkflowService;
use App\Services\LoanDisbursementOrchestrator;
use App\Services\LoanQualificationService;
use App\Services\RepaymentPostingService;
use Database\Seeders\ValuationPricingDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LendingJourneyFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function branch(): Branch
    {
        return Branch::create([
            'code' => 'LJ'.random_int(10, 99),
            'name' => 'Lending Journey',
            'region' => 'Dar',
            'is_active' => true,
        ]);
    }

    private function staff(string $role = 'admin'): User
    {
        $branch = $this->branch();

        return User::factory()->create([
            'role' => $role,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    private function application(User $actor, array $overrides = []): LoanApplication
    {
        $customer = Customer::create([
            'customer_number' => 'CU-LJ-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Mushi',
            'phone' => '25571234'.random_int(1000, 9999),
            'branch_id' => $actor->branch_id,
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-LJ-'.random_int(100, 999),
            'name' => 'Journey Loan',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 8_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'repayment_cadence' => 'monthly',
        ]);

        return LoanApplication::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $actor->branch_id,
            'application_number' => 'APP-LJ-'.random_int(1000, 9999),
            'status' => 'pre_approved',
            'current_stage' => 'pre_approval',
            'recommendation_type' => 'approve',
            'recommended_amount' => 500_000,
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'funding_source' => 'internal',
        ], $overrides));
    }

    public function test_committee_approve_without_limits_goes_straight_to_offer(): void
    {
        $committee = $this->staff('admin');
        $application = $this->application($committee);

        $this->assertFalse(app(CreditAuthorityService::class)->managementApprovalRequired($application));

        app(LoanApplicationWorkflowService::class)->transition(
            $application,
            $committee,
            'approve',
            'Committee approve',
            true,
            null,
            null,
            null,
            null,
            null,
            'aligns_with_screening',
            'Validated.',
        );

        $application->refresh();
        $this->assertSame('approval', $application->current_stage);
        $this->assertSame('approved', $application->status);
        $this->assertNotNull($application->loan);
        $this->assertSame('pending', $application->loan->status);
        $this->assertSame('post_approval', app(LendingJourneyService::class)->forApplication($application)['state']);
    }

    public function test_committee_approve_with_dual_control_waits_for_management_without_creating_a_loan(): void
    {
        ApprovalLimit::create([
            'role_code' => 'credit_committee',
            'action' => 'loan_approve',
            'min_amount' => 0,
            'max_amount' => 10_000_000,
            'currency' => 'TZS',
            'requires_dual_control' => true,
            'is_active' => true,
        ]);

        $committee = $this->staff('admin');
        $application = $this->application($committee);
        $this->assertTrue(app(CreditAuthorityService::class)->managementApprovalRequired($application));

        app(LoanApplicationWorkflowService::class)->transition(
            $application,
            $committee,
            'approve',
            'Committee approve',
            true,
            null,
            null,
            null,
            null,
            null,
            'aligns_with_screening',
            'Validated.',
        );

        $application->refresh();
        $this->assertSame('awaiting_management', $application->current_stage);
        $this->assertSame('pre_approved', $application->status);
        $this->assertNull($application->loan);
        $journey = app(LendingJourneyService::class)->forApplication($application);
        $this->assertSame('waiting_management', $journey['state']);
        $this->assertSame('management', $journey['waiting_on']);
    }

    public function test_grade_and_trust_do_not_skip_management_when_matrix_requires_it(): void
    {
        ApprovalLimit::create([
            'role_code' => 'credit_committee',
            'action' => 'loan_approve',
            'min_amount' => 0,
            'max_amount' => 100_000,
            'currency' => 'TZS',
            'requires_dual_control' => false,
            'is_active' => true,
        ]);
        ApprovalLimit::create([
            'role_code' => 'manager',
            'action' => 'loan_approve',
            'min_amount' => 100_001,
            'max_amount' => 10_000_000,
            'currency' => 'TZS',
            'requires_dual_control' => false,
            'is_active' => true,
        ]);

        $application = $this->application($this->staff(), [
            'requested_amount' => 2_500_000,
            'recommended_amount' => 2_500_000,
        ]);

        $this->assertTrue(app(CreditAuthorityService::class)->managementApprovalRequired($application->fresh()));
    }

    public function test_management_approve_issues_offer_and_pending_loan(): void
    {
        $manager = $this->staff('admin');
        $application = $this->application($manager, [
            'current_stage' => 'awaiting_management',
            'status' => 'pre_approved',
        ]);

        app(LoanApplicationWorkflowService::class)->transition(
            $application,
            $manager,
            'management_approve',
            'Management approve',
            true,
            null,
            null,
            null,
            null,
            null,
            'aligns_with_screening',
            'Within policy.',
        );

        $application->refresh();
        $this->assertSame('approval', $application->current_stage);
        $this->assertNotNull($application->loan);
        $this->assertSame('pending', $application->loan->status);
    }

    public function test_paid_valuation_fee_does_not_resurrect_waiting_on_borrower(): void
    {
        $this->seed(ValuationPricingDefaultsSeeder::class);
        $actor = $this->staff();
        $application = $this->application($actor, [
            'current_stage' => 'screening',
            'status' => 'under_review',
        ]);
        $asset = CustomerAsset::create([
            'customer_id' => $application->customer_id,
            'asset_type' => 'vehicle',
            'label' => 'Rav4',
            'is_active' => true,
        ]);
        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $asset->id,
            'asset_type' => 'vehicle',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
        ]);
        $application->update([
            'screening_payload' => [
                'collateral_secure' => [
                    'requested_at' => now()->toIso8601String(),
                    'status' => CollateralSecureService::STATUS_SECURED,
                    'customer_asset_id' => $asset->id,
                    'path' => CollateralSecureService::PATH_SCREENING_VALUATION,
                ],
            ],
        ]);

        CustomerPayment::create([
            'reference' => 'VAL-LJ-'.random_int(1000, 9999),
            'customer_id' => $application->customer_id,
            'payment_type' => 'valuation_fee',
            'payment_method' => 'mobile_money',
            'amount' => 50_000,
            'currency' => 'TZS',
            'status' => 'verified',
            'paid_at' => now(),
            'source_type' => $application->getMorphClass(),
            'source_id' => $application->id,
        ]);

        $secure = app(CollateralSecureService::class);
        $this->assertFalse($secure->needsValuationFeePayment($application->fresh()));
        $this->assertNotSame(
            CollateralSecureService::STATUS_AWAITING_VALUATION_FEE,
            $secure->state($application->fresh())['status'] ?? null
        );
    }

    public function test_latched_post_approval_fee_does_not_reopen(): void
    {
        $application = $this->application($this->staff(), [
            'current_stage' => 'approval',
            'status' => 'approved',
            'offer_status' => 'accepted',
            'borrower_completed_steps' => ['offer_accepted', 'post_approval_fees_paid'],
        ]);
        LoanApplicationPostApprovalFee::create([
            'loan_application_id' => $application->id,
            'code' => 'PAF',
            'name' => 'Processing',
            'fee_type' => 'fixed',
            'calculated_amount' => 10_000,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        $readiness = app(ApplicationDisbursementReadinessService::class);
        $this->assertFalse($readiness->needsPostApprovalFees($application->fresh()));
        $this->assertTrue($readiness->feesPaid($application->fresh()));
    }

    public function test_failed_disbursement_keeps_loan_pending(): void
    {
        $actor = $this->staff();
        $application = $this->application($actor, [
            'current_stage' => 'disbursement',
            'status' => 'approved',
            'offer_status' => 'accepted',
        ]);
        $loan = Loan::create([
            'customer_id' => $application->customer_id,
            'loan_product_id' => $application->loan_product_id,
            'loan_number' => 'LN-LJ-FAIL',
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'interest_rate' => 0.15,
            'tenure_months' => 6,
            'outstanding_balance' => 500_000,
            'status' => 'pending',
        ]);

        $this->mock(CapitalPartnerAllocationService::class, function ($mock): void {
            $mock->shouldReceive('allocateForLoan')->andThrow(new \RuntimeException('PSP declined'));
            $mock->shouldReceive('releaseAllocationForLoan')->zeroOrMoreTimes();
            $mock->shouldReceive('reverseAllocationForLoan')->zeroOrMoreTimes();
        });

        try {
            app(LoanDisbursementOrchestrator::class)->disburse($loan, $actor);
            $this->fail('Expected disbursement to fail');
        } catch (\Throwable) {
            // expected
        }

        $loan->refresh();
        $this->assertSame('pending', $loan->status);
        $this->assertTrue($loan->disbursements()->where('status', Disbursement::STATUS_FAILED)->exists());
        $this->assertFalse($loan->disbursements()->where('status', Disbursement::STATUS_RELEASED)->exists());
    }

    public function test_successful_disbursement_is_idempotent_and_activates_only_on_release(): void
    {
        $actor = $this->staff();
        $application = $this->application($actor, [
            'current_stage' => 'disbursement',
            'status' => 'approved',
            'offer_status' => 'accepted',
            'disbursement_details_confirmed_at' => now(),
        ]);
        $loan = Loan::create([
            'customer_id' => $application->customer_id,
            'loan_product_id' => $application->loan_product_id,
            'loan_number' => 'LN-LJ-OK',
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'interest_rate' => 0.15,
            'tenure_months' => 6,
            'outstanding_balance' => 500_000,
            'status' => 'pending',
        ]);

        $this->mock(CapitalPartnerAllocationService::class, function ($mock): void {
            $mock->shouldReceive('allocateForLoan')->andReturnNull();
            $mock->shouldReceive('releaseAllocationForLoan')->zeroOrMoreTimes();
            $mock->shouldReceive('reverseAllocationForLoan')->zeroOrMoreTimes();
        });

        $first = app(LoanDisbursementOrchestrator::class)->disburse($loan, $actor);
        $this->assertSame('active', $first->status);
        $this->assertSame(1, $first->disbursements()->where('status', Disbursement::STATUS_RELEASED)->count());

        $second = app(LoanDisbursementOrchestrator::class)->disburse($first, $actor);
        $this->assertSame('active', $second->status);
        $this->assertSame(1, $second->disbursements()->where('status', Disbursement::STATUS_RELEASED)->count());
    }

    public function test_closed_loan_completion_summary(): void
    {
        $actor = $this->staff();
        $application = $this->application($actor);
        $loan = Loan::create([
            'customer_id' => $application->customer_id,
            'loan_product_id' => $application->loan_product_id,
            'loan_application_id' => $application->id,
            'loan_number' => 'LN-LJ-DONE',
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'interest_rate' => 0.15,
            'tenure_months' => 6,
            'outstanding_balance' => 0,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $summary = app(LendingJourneyService::class)->completionSummary($loan);
        $this->assertSame(500_000.0, $summary['amount_borrowed']);
        $this->assertNotNull($summary['completed_at']);
    }

    public function test_management_refer_back_returns_file_to_committee(): void
    {
        $manager = $this->staff('admin');
        $application = $this->application($manager, [
            'current_stage' => 'awaiting_management',
            'status' => 'pre_approved',
        ]);

        app(LoanApplicationWorkflowService::class)->transition(
            $application,
            $manager,
            'refer_back',
            'Need committee to revisit the conditions.',
            false,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'committee',
        );

        $application->refresh();
        $this->assertSame('pre_approval', $application->current_stage);
        $this->assertSame('pre_approved', $application->status);
        $this->assertNull(data_get($application->credit_appraisal_payload, 'committee_approval'));
    }

    public function test_management_only_cannot_open_screening_or_committee_files(): void
    {
        $manager = $this->staff('manager');
        $screening = $this->application($this->staff(), [
            'current_stage' => 'screening',
            'status' => 'under_review',
        ]);
        $screening->update(['branch_id' => $manager->branch_id]);
        $committee = $this->application($this->staff(), [
            'current_stage' => 'pre_approval',
            'status' => 'pre_approved',
        ]);
        $committee->update(['branch_id' => $manager->branch_id]);
        $awaiting = $this->application($this->staff(), [
            'current_stage' => 'awaiting_management',
            'status' => 'pre_approved',
        ]);
        $awaiting->update(['branch_id' => $manager->branch_id]);

        $this->actingAs($manager, 'admin')
            ->get(route('admin.loan-applications.show', $screening))
            ->assertForbidden();

        $this->actingAs($manager, 'admin')
            ->get(route('admin.loan-applications.show', $committee))
            ->assertForbidden();

        $this->actingAs($manager, 'admin')
            ->get(route('admin.loan-applications.show', $awaiting))
            ->assertOk()
            ->assertSee('Management approval pack', false)
            ->assertSee('Authorize this committee-approved facility', false)
            ->assertDontSee('Record the screening recommendation', false);
    }

    public function test_destination_opens_only_after_fees_and_stays_latched_when_a_new_account_is_added(): void
    {
        $actor = $this->staff();
        $borrower = User::factory()->create(['role' => 'borrower']);
        $application = $this->application($actor, [
            'current_stage' => 'approval',
            'status' => 'approved',
            'offer_status' => 'accepted',
        ]);
        $application->customer->update([
            'user_id' => $borrower->id,
            'first_name' => 'Asha',
            'last_name' => 'Mushi',
        ]);

        $readiness = app(ApplicationDisbursementReadinessService::class);
        $this->assertTrue($readiness->needsDisbursementDetailsConfirmation($application->fresh()));
        $this->assertFalse($readiness->needsContractSignature($application->fresh()));

        $account = CustomerDisbursementAccount::create([
            'customer_id' => $application->customer_id,
            'type' => 'mobile_money',
            'account_name' => 'Asha Mushi',
            'mobile_provider' => 'mpesa',
            'mobile_number' => '255712348011',
            'is_default' => true,
        ]);

        app(CustomerDisbursementDetailsService::class)->confirmForApplication(
            $application->fresh(),
            $application->customer->fresh(),
            $account,
        );

        $application = $application->fresh();
        $this->assertFalse($readiness->needsDisbursementDetailsConfirmation($application));
        $this->assertContains('disbursement_account_confirmed', $application->borrower_completed_steps ?? []);
        $this->assertNotEmpty($application->disbursement_details_snapshot['method'] ?? null);

        app(CustomerDisbursementDetailsService::class)->createAccount($application->customer->fresh(), [
            'type' => 'bank',
            'account_name' => 'Asha Mushi',
            'bank_name' => 'CRDB',
            'account_number' => '1234567890',
            'bank_branch' => 'Kariakoo',
        ]);

        $application = $application->fresh();
        $this->assertNotNull($application->disbursement_details_confirmed_at);
        $this->assertSame($account->id, $application->disbursement_account_id);
        $this->assertFalse($readiness->needsDisbursementDetailsConfirmation($application));
        $this->assertTrue($readiness->disbursementDetailsConfirmed($application));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CustomerDisbursementDetailsService::class)->deleteAccount(
            $application->customer->fresh(),
            $account->fresh(),
        );
    }

    public function test_closed_loan_boosts_repeat_borrower_and_gold_uses_welcome_back_journey(): void
    {
        $actor = $this->staff();
        $application = $this->application($actor);
        $customer = $application->customer;
        $customer->update([
            'monthly_income' => 500_000,
            'grade' => 'gold',
        ]);

        $withoutHistory = app(LoanQualificationService::class)->calculate($customer->fresh());

        Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $application->loan_product_id,
            'loan_application_id' => $application->id,
            'loan_number' => 'LN-LJ-REPEAT',
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'interest_rate' => 0.15,
            'tenure_months' => 6,
            'outstanding_balance' => 0,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $withHistory = app(LoanQualificationService::class)->calculate($customer->fresh());
        $this->assertGreaterThan($withoutHistory['amount'], $withHistory['amount']);
        $this->assertTrue(collect($withHistory['factors'])->contains(
            fn (array $factor) => ($factor['label'] ?? '') === 'Repayment history'
        ));
        $this->assertSame('welcome_back', app(GradeBenefitService::class)->repeatJourney($customer->fresh()));
        $this->assertSame('completed', app(LendingJourneyService::class)->forApplication($application->fresh())['state']);
    }

    public function test_overdue_installment_cures_to_active_when_repayment_is_posted(): void
    {
        $actor = $this->staff();
        $application = $this->application($actor, [
            'current_stage' => 'disbursement',
            'status' => 'disbursed',
        ]);
        $loan = Loan::create([
            'customer_id' => $application->customer_id,
            'loan_product_id' => $application->loan_product_id,
            'loan_application_id' => $application->id,
            'loan_number' => 'LN-LJ-CURE',
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'interest_rate' => 0.15,
            'tenure_months' => 6,
            'outstanding_balance' => 50_000,
            'status' => 'arrears',
        ]);
        RepaymentSchedule::create([
            'loan_id' => $loan->id,
            'installment_no' => 1,
            'due_date' => now()->subDays(8)->toDateString(),
            'principal_due' => 40_000,
            'interest_due' => 10_000,
            'total_due' => 50_000,
            'amount_paid' => 0,
            'status' => 'overdue',
        ]);
        RepaymentSchedule::create([
            'loan_id' => $loan->id,
            'installment_no' => 2,
            'due_date' => now()->addMonth()->toDateString(),
            'principal_due' => 40_000,
            'interest_due' => 10_000,
            'total_due' => 50_000,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        $servicing = app(ActiveLoanServicingService::class)->forLoan($loan->fresh(['repaymentSchedules']));
        $this->assertTrue($servicing['in_arrears']);
        $this->assertSame(50_000.0, (float) $servicing['amount_in_arrears']);

        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'reference' => 'RCP-LJ-CURE',
            'channel' => 'mobile_money',
            'amount' => 50_000,
            'status' => 'received',
            'principal_component' => 40_000,
            'interest_component' => 10_000,
            'paid_at' => now(),
        ]);

        app(RepaymentPostingService::class)->post($repayment);

        $loan->refresh();
        $this->assertSame('active', $loan->status);
        $this->assertFalse(
            app(ActiveLoanServicingService::class)->forLoan($loan->fresh(['repaymentSchedules']))['in_arrears']
        );
    }

    public function test_lending_fees_and_repayments_use_shared_payment_show(): void
    {
        $actor = $this->staff();
        $borrower = User::factory()->create(['role' => 'borrower']);
        $application = $this->application($actor, [
            'current_stage' => 'approval',
            'status' => 'approved',
            'offer_status' => 'accepted',
        ]);
        $application->customer->update(['user_id' => $borrower->id]);

        $feePayment = CustomerPayment::create([
            'customer_id' => $application->customer_id,
            'payment_type' => 'post_approval_fee',
            'payment_method' => 'mobile_money',
            'amount' => 10_000,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'reference' => 'PAF-LJ-SHOW',
            'source_type' => $application->getMorphClass(),
            'source_id' => $application->id,
        ]);

        $this->actingAs($borrower)
            ->get(route('site.borrower.payments.show', $feePayment))
            ->assertOk()
            ->assertSee('PAF-LJ-SHOW', false);

        $loan = Loan::create([
            'customer_id' => $application->customer_id,
            'loan_product_id' => $application->loan_product_id,
            'loan_application_id' => $application->id,
            'loan_number' => 'LN-LJ-PAY',
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'interest_rate' => 0.15,
            'tenure_months' => 6,
            'outstanding_balance' => 400_000,
            'status' => 'active',
        ]);

        $redirect = $this->actingAs($borrower)
            ->post(route('site.borrower.payments.store'), [
                'loan_id' => $loan->id,
                'amount' => 25_000,
                'payment_method' => 'mobile_money',
                'mobile_number' => '0712345678',
            ]);

        $latest = CustomerPayment::query()
            ->where('customer_id', $application->customer_id)
            ->where('payment_type', 'loan_repayment')
            ->latest('id')
            ->firstOrFail();

        $redirect->assertRedirect(route('site.borrower.payments.show', $latest));
    }
}
