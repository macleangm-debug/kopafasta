<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\CapacityAutoRejectService;
use App\Services\CreditEligibilityPolicyService;
use App\Services\GroupLendingService;
use App\Services\PartnerEarningsCatalogService;
use App\Services\ScreeningSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScreeningSequenceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('underwriting.enable_automatic_rejection', true);
        Setting::set('underwriting.enable_capacity_auto_reject', true);
        Setting::set('underwriting.capacity_auto_reject_delay_hours', 12);
        Setting::set('underwriting.verified_capacity_auto_reject_delay_hours', 6);
        Setting::set('underwriting.group_member_hard_fail_action', 'replace_member');
    }

    public function test_gate_1_fail_parks_for_configured_12_hours_and_cannot_be_sent_to_committee(): void
    {
        $application = $this->individual(100_000, 2_000_000);
        $service = app(CapacityAutoRejectService::class);
        $state = $service->evaluateAndPark($application->fresh(['customer', 'product']));

        $this->assertNotNull($state);
        $this->assertSame('declared', $state['gate'] ?? null);
        $this->assertSame(12, (int) $state['delay_hours']);
        $this->assertTrue($service->isPending($application->fresh()));

        $snap = app(ScreeningSequenceService::class)->snapshot($application->fresh());
        $this->assertFalse($snap['later_unlocked']);
        $this->assertTrue($snap['pending_rejection']);
        $this->assertFalse($snap['declared']['pass']);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.workflow', $application), [
                'action' => 'submit_recommendation',
                'recommendation_type' => 'approve',
                'remarks' => 'Trying to override a hard Gate 1 fail.',
            ])
            ->assertSessionHasErrors();
    }

    public function test_gate_1_pass_unlocks_gate_2_and_locks_later_gates(): void
    {
        $application = $this->individual(5_000_000, 200_000);
        $service = app(CapacityAutoRejectService::class);
        $this->assertNull($service->evaluateAndPark($application->fresh(['customer', 'product'])));

        $snap = app(ScreeningSequenceService::class)->snapshot($application->fresh());
        $this->assertTrue($snap['declared']['pass']);
        $this->assertFalse($snap['verified']['pass']);
        $this->assertFalse($snap['later_unlocked']);
        $this->assertTrue(app(ScreeningSequenceService::class)->gateUnlocked($application->fresh(), 'income'));
        $this->assertFalse(app(ScreeningSequenceService::class)->gateUnlocked($application->fresh(), 'collateral'));
        $this->assertFalse(app(ScreeningSequenceService::class)->gateUnlocked($application->fresh(), 'crb'));
        $this->assertFalse(app(ScreeningSequenceService::class)->gateUnlocked($application->fresh(), 'identity'));
    }

    public function test_gate_2_pass_skips_na_collateral_and_unlocks_crb(): void
    {
        $application = $this->individual(5_000_000, 200_000);
        $payload = $application->screening_payload ?? [];
        $payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.income_evidence'] = [
            'verdict' => 'pass',
            'source' => 'system',
            'statement_deposits_total' => 30_000_000,
            'statement_months' => 6,
            'statement_monthly' => 5_000_000,
        ];
        $application->update(['screening_payload' => $payload]);

        $snap = app(ScreeningSequenceService::class)->snapshot($application->fresh(['customer', 'product']));
        $this->assertTrue($snap['declared']['pass']);
        $this->assertTrue($snap['verified']['pass']);
        $this->assertTrue($snap['later_unlocked']);
        $this->assertTrue($snap['unlocked']['crb'] ?? false);
        $this->assertFalse($snap['unlocked']['collateral'] ?? true);
        $this->assertFalse($snap['unlocked']['identity'] ?? true);
        $this->assertStringContainsString('Complete CRB', (string) ($snap['next_action']['label'] ?? ''));
        $this->assertStringNotContainsString('Continue to Identity', (string) ($snap['next_action']['label'] ?? ''));
    }

    public function test_gate_2_pass_unlocks_crb_before_collateral_when_required(): void
    {
        $application = $this->individual(5_000_000, 200_000, true);
        $payload = $application->screening_payload ?? [];
        $payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.income_evidence'] = [
            'verdict' => 'pass',
            'source' => 'system',
            'statement_deposits_total' => 30_000_000,
            'statement_months' => 6,
            'statement_monthly' => 5_000_000,
        ];
        $application->update(['screening_payload' => $payload]);

        $snap = app(ScreeningSequenceService::class)->snapshot($application->fresh(['customer', 'product']));
        $this->assertTrue($snap['unlocked']['crb'] ?? false);
        $this->assertFalse($snap['unlocked']['collateral'] ?? true);
        $this->assertFalse($snap['unlocked']['identity'] ?? true);
        $this->assertStringContainsString('Complete CRB', (string) ($snap['next_action']['label'] ?? ''));
    }

    public function test_required_missing_collateral_uses_secure_journey_not_document_request(): void
    {
        $application = $this->individual(5_000_000, 200_000, true);
        $payload = $application->screening_payload ?? [];
        $payload['guided']['seen_gates']['declared'] = true;
        $payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.income_evidence'] = [
            'verdict' => 'pass',
            'source' => 'system',
            'statement_deposits_total' => 30_000_000,
            'statement_months' => 6,
            'statement_monthly' => 5_000_000,
        ];
        $application->update(['screening_payload' => $payload]);

        $beforeCrb = app(\App\Services\ScreeningNextActionService::class)
            ->forApplication($application->fresh(['customer', 'product', 'documentRequests']));
        $this->assertNotSame('collateral_secure', $beforeCrb['step']['type'] ?? null);

        $this->passBorrowerCrb($application);

        $next = app(\App\Services\ScreeningNextActionService::class)
            ->forApplication($application->fresh(['customer', 'product', 'documentRequests']));
        $this->assertSame('do_now', $next['bucket']);
        $this->assertSame('collateral_secure', $next['step']['type'] ?? null);
        $this->assertSame('collateral', $next['step']['gate'] ?? null);
        $this->assertStringContainsString('not assign a valuer', (string) ($next['step']['prompt'] ?? ''));
    }

    public function test_awaiting_collateral_pledge_uses_waiting_for_collateral_kind(): void
    {
        $application = $this->individual(5_000_000, 200_000, true);
        $payload = $application->screening_payload ?? [];
        $payload['collateral_secure'] = [
            'requested_at' => now()->toIso8601String(),
            'status' => 'awaiting_borrower_has_collateral',
        ];
        $application->update(['screening_payload' => $payload]);

        $next = app(\App\Services\ScreeningNextActionService::class)
            ->forApplication($application->fresh(['customer', 'product', 'documentRequests']));
        $this->assertSame('waiting', $next['bucket']);
        $this->assertSame('collateral', $next['waiting']['kind'] ?? null);
        $this->assertSame('Waiting for collateral', $next['waiting']['label'] ?? null);
    }

    public function test_document_waiting_buckets_split_member_guarantor_leader_and_borrower(): void
    {
        $borrowerApp = $this->individual(5_000_000, 200_000);
        LoanApplicationDocumentRequest::create([
            'loan_application_id' => $borrowerApp->id,
            'label' => 'Updated National ID',
            'type' => 'document',
            'status' => 'pending',
            'subject_kind' => 'borrower',
            'due_at' => now()->addDays(7),
        ]);
        $next = app(\App\Services\ScreeningNextActionService::class)
            ->forApplication($borrowerApp->fresh(['customer', 'product', 'documentRequests']));
        $this->assertSame('waiting', $next['bucket']);
        $this->assertSame('document', $next['waiting']['kind'] ?? null);
        $this->assertSame('Waiting for document', $next['waiting']['label'] ?? null);

        $memberApp = $this->individual(5_000_000, 200_000);
        LoanApplicationDocumentRequest::create([
            'loan_application_id' => $memberApp->id,
            'label' => 'Updated National ID',
            'type' => 'document',
            'status' => 'pending',
            'subject_kind' => 'member',
            'due_at' => now()->addDays(7),
        ]);
        $next = app(\App\Services\ScreeningNextActionService::class)
            ->forApplication($memberApp->fresh(['customer', 'product', 'documentRequests']));
        $this->assertSame('member', $next['waiting']['kind'] ?? null);
        $this->assertSame('Waiting for member', $next['waiting']['label'] ?? null);

        $guarantorApp = $this->individual(5_000_000, 200_000);
        LoanApplicationDocumentRequest::create([
            'loan_application_id' => $guarantorApp->id,
            'label' => 'Proof of residence',
            'type' => 'document',
            'status' => 'pending',
            'subject_kind' => 'guarantor',
            'due_at' => now()->addDays(7),
        ]);
        $next = app(\App\Services\ScreeningNextActionService::class)
            ->forApplication($guarantorApp->fresh(['customer', 'product', 'documentRequests']));
        $this->assertSame('guarantor', $next['waiting']['kind'] ?? null);
        $this->assertSame('Waiting for guarantor', $next['waiting']['label'] ?? null);
    }

    public function test_grandfathered_identity_file_is_not_forced_behind_new_g3_and_keeps_valuation_fee(): void
    {
        $application = $this->individual(5_000_000, 200_000, true);
        $payload = $application->screening_payload ?? [];
        $payload['screening_checklist']['by_subject']['borrower']['items']['identity.face_vs_nida'] = [
            'verdict' => 'pass',
            'source' => 'human',
        ];
        $payload['collateral_secure'] = [
            'requested_at' => now()->subDays(10)->toIso8601String(),
            'status' => 'awaiting_valuer',
            'path' => 'screening_valuation',
            'valuation_fee_due' => 85_000,
            'valuation_fee_paid_at' => now()->subDays(2)->toIso8601String(),
            'valuation' => ['status' => 'in_progress'],
        ];
        $application->update(['screening_payload' => $payload]);

        $before = $application->fresh()->screening_payload['collateral_secure'];
        $snap = app(ScreeningSequenceService::class)->snapshot($application->fresh());

        $this->assertTrue($snap['grandfathered']);
        $this->assertTrue($snap['unlocked']['collateral'] ?? false);
        $this->assertTrue($snap['unlocked']['crb'] ?? false);
        $this->assertTrue($snap['unlocked']['identity'] ?? false);
        $this->assertSame($before, $application->fresh()->screening_payload['collateral_secure']);
        $this->assertSame(85_000, (int) data_get($application->fresh()->screening_payload, 'collateral_secure.valuation_fee_due'));
        $this->assertSame('awaiting_valuer', data_get($application->fresh()->screening_payload, 'collateral_secure.status'));
    }

    public function test_collateral_policy_modes_never_always_and_above_amount(): void
    {
        $product = LoanProduct::create([
            'code' => 'IL-POL-'.random_int(100, 999),
            'name' => 'Policy Individual',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);
        $policy = app(\App\Services\LoanPolicyService::class);

        Setting::set('loan.collateral_requirement_mode', 'never');
        Setting::set('loan.collateral_required_above', 200_000);
        $this->assertFalse($policy->requiresCollateralForApplication($product, 5_000_000));

        Setting::set('loan.collateral_requirement_mode', 'always');
        $this->assertTrue($policy->requiresCollateralForApplication($product, 1));

        Setting::set('loan.collateral_requirement_mode', 'above_amount');
        $this->assertFalse($policy->requiresCollateralForApplication($product, 199_999));
        $this->assertTrue($policy->requiresCollateralForApplication($product, 200_000));

        $product->requires_collateral = true;
        Setting::set('loan.collateral_requirement_mode', 'never');
        $this->assertTrue($policy->requiresCollateralForApplication($product, 1));
    }

    public function test_gate_2_system_fail_uses_settings_delay_and_freezes_deadline(): void
    {
        $application = $this->individual(5_000_000, 200_000);
        $this->keyStatements($application, 1_000);

        Setting::set('underwriting.verified_capacity_auto_reject_delay_hours', 6);
        $state = app(CapacityAutoRejectService::class)->evaluateVerifiedAndPark($application->fresh(['customer', 'product']));
        $this->assertNotNull($state);
        $this->assertSame('verified', $state['gate'] ?? null);
        $this->assertSame(6, (int) $state['delay_hours']);
        $frozen = $state['auto_reject_at'];

        Setting::set('underwriting.verified_capacity_auto_reject_delay_hours', 24);
        $again = app(CapacityAutoRejectService::class)->evaluateVerifiedAndPark($application->fresh());
        $this->assertSame($frozen, $again['auto_reject_at'] ?? null);
        $this->assertSame(6, (int) data_get($application->fresh()->screening_payload, 'capacity_auto_reject.delay_hours'));
    }

    public function test_qualitative_concern_does_not_start_gate_2_timer(): void
    {
        $application = $this->individual(5_000_000, 200_000);
        $payload = $application->screening_payload ?? [];
        $payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.bank_or_mobile_money'] = [
            'verdict' => 'fail',
            'fail_reason_code' => 'custom',
            'fail_reason_custom' => 'Odd pattern noted',
            'source' => 'human',
        ];
        $application->update(['screening_payload' => $payload]);

        $this->assertNull(app(CapacityAutoRejectService::class)->evaluateVerifiedAndPark($application->fresh(['customer', 'product'])));
        $this->assertFalse(app(CapacityAutoRejectService::class)->isPending($application->fresh()));
    }

    public function test_duplicate_fire_due_does_not_double_reject(): void
    {
        $application = $this->individual(100_000, 2_000_000);
        $service = app(CapacityAutoRejectService::class);
        $service->evaluateAndPark($application->fresh(['customer', 'product']));
        Carbon::setTestNow(now()->addHours(13));
        $this->assertCount(1, $service->fireDue());
        $this->assertCount(0, $service->fireDue());
        $this->assertSame('rejected', $application->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_existing_file_with_later_gate_work_stays_unlocked(): void
    {
        $application = $this->individual(5_000_000, 200_000);
        $payload = $application->screening_payload ?? [];
        $payload['screening_checklist']['by_subject']['borrower']['items']['identity.face_vs_nida'] = [
            'verdict' => null,
            'at' => now()->toIso8601String(),
        ];
        $payload['screening_checklist']['by_subject']['member:3']['items']['identity.nida_vs_dob'] = [
            'verdict' => 'fail',
            'fail_reason_code' => 'nida_impossible',
            'source' => 'system',
        ];
        $payload['collateral_secure']['valuation']['status'] = 'completed';
        $application->update(['screening_payload' => $payload]);

        $snap = app(ScreeningSequenceService::class)->snapshot($application->fresh());
        $this->assertTrue($snap['grandfathered']);
        $this->assertTrue($snap['later_unlocked']);
        $this->assertFalse($snap['pending_rejection']);
        $this->assertSame('income', collect($snap['sequence'])->firstWhere('key', 'declared')['desk_gate'] ?? null);
        $this->assertSame('collateral', collect($snap['sequence'])->firstWhere('key', 'collateral')['desk_gate'] ?? null);
    }

    public function test_group_member_fail_does_not_park_the_whole_application(): void
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);
        $product = LoanProduct::create([
            'code' => 'GL',
            'name' => 'Group Loan',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 200_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);

        $leader = $this->person('Leader', 'G', '255710000001', 5_000_000);
        $ok = $this->person('Ok', 'Member', '255710000002', 5_000_000);
        $weak = $this->person('Weak', 'Member', '255710000003', 1_000);

        $application = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-SEQ-GRP',
            'requested_amount' => 900_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);

        app(GroupLendingService::class)->createForApplication(
            $application,
            [
                ['customer_id' => $leader->id, 'requested_amount' => 300_000, 'role' => 'leader'],
                ['customer_id' => $ok->id, 'requested_amount' => 300_000, 'role' => 'member'],
                ['customer_id' => $weak->id, 'requested_amount' => 300_000, 'role' => 'member'],
            ],
            'Seq Group',
            'Business',
        );

        $parked = app(CapacityAutoRejectService::class)->evaluateAndPark($application->fresh(['customer', 'product', 'loanGroup.members.customer']));
        $this->assertNull($parked);

        $policy = app(CreditEligibilityPolicyService::class)->evaluate($application->fresh(['customer', 'product', 'loanGroup.members.customer']));
        $this->assertSame(CreditEligibilityPolicyService::ACTION_RESOLVE_MEMBERS, $policy['application_action']);
        $this->assertSame('replace_group_member', $policy['resolution']['resolution'] ?? null);
        $this->assertTrue($policy['resolution']['blocking'] ?? false);
    }

    public function test_continue_with_eligible_members_marks_failed_member_ineligible(): void
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);
        $product = LoanProduct::create([
            'code' => 'GL-CONT',
            'name' => 'Group Loan Continue',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 200_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);

        $leader = $this->person('Leader', 'C', '255710000011', 5_000_000);
        $ok = $this->person('Ok', 'One', '255710000012', 5_000_000);
        $ok2 = $this->person('Ok', 'Two', '255710000013', 5_000_000);
        $weak = $this->person('Weak', 'Drop', '255710000014', 1_000);

        $application = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-SEQ-CONT',
            'requested_amount' => 1_200_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);

        app(GroupLendingService::class)->createForApplication(
            $application,
            [
                ['customer_id' => $leader->id, 'requested_amount' => 300_000, 'role' => 'leader'],
                ['customer_id' => $ok->id, 'requested_amount' => 300_000, 'role' => 'member'],
                ['customer_id' => $ok2->id, 'requested_amount' => 300_000, 'role' => 'member'],
                ['customer_id' => $weak->id, 'requested_amount' => 300_000, 'role' => 'member'],
            ],
            'Cont Group',
            'Business',
        );

        $policy = app(CreditEligibilityPolicyService::class)->evaluate($application->fresh(['customer', 'product', 'loanGroup.members.customer']));
        $this->assertTrue($policy['resolution']['allow_continue_without_failed'] ?? false);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        app(\App\Services\GroupLoanMemberReviewService::class)->continueWithEligibleMembers(
            $application->fresh(['loanGroup.members.customer']),
            $admin,
            'Member failed Gate 1 and remaining size still meets the minimum.',
        );

        $weakMember = $application->fresh()->loanGroup->members->firstWhere('customer_id', $weak->id);
        $this->assertSame('ineligible', $weakMember->member_status);
        $this->assertSame('submitted', $application->fresh()->status);

        $after = app(CreditEligibilityPolicyService::class)->evaluate($application->fresh(['customer', 'product', 'loanGroup.members.customer']));
        $this->assertSame(CreditEligibilityPolicyService::ACTION_CONTINUE, $after['application_action']);
    }

    public function test_borrower_fail_is_not_rescued_by_a_strong_guarantor(): void
    {
        $application = $this->individual(1_000, 2_000_000);
        $policy = app(CreditEligibilityPolicyService::class)->evaluate($application->fresh(['customer', 'product']));
        $this->assertSame(CreditEligibilityPolicyService::ACTION_PENDING_REJECTION, $policy['application_action']);
        $this->assertStringContainsString('guarantor cannot rescue', strtolower($policy['reason'] ?? ''));
    }

    public function test_partner_earnings_catalog_covers_current_paid_types(): void
    {
        $types = collect(app(PartnerEarningsCatalogService::class)->earningPartnerTypes())->pluck('partner_type')->all();
        foreach (['valuer', 'recovery', 'affiliate', 'insurance', 'capital'] as $type) {
            $this->assertContains($type, $types);
        }
        $capital = collect(app(PartnerEarningsCatalogService::class)->earningPartnerTypes())
            ->firstWhere('partner_type', 'capital');
        $this->assertSame('capital_agreement', $capital['formula'] ?? null);
        $this->assertSame('shared', collect(app(PartnerEarningsCatalogService::class)->earningPartnerTypes())
            ->firstWhere('partner_type', 'valuer')['wallet'] ?? null);
    }

    private function passBorrowerCrb(LoanApplication $application): void
    {
        $payload = $application->screening_payload ?? [];
        foreach (['identity.name_vs_crb', 'identity.marital_vs_crb', 'credit_file.crb_reviewed'] as $key) {
            $payload['screening_checklist']['by_subject']['borrower']['items'][$key] = [
                'verdict' => 'pass',
                'source' => 'system',
            ];
        }
        $application->update(['screening_payload' => $payload]);
    }

    private function individual(float $income, float $amount, bool $requiresCollateral = false): LoanApplication
    {
        $product = LoanProduct::create([
            'code' => 'IL-SEQ-'.random_int(100, 999),
            'name' => 'Seq Individual',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_collateral' => $requiresCollateral,
        ]);
        $customer = $this->person('Seq', 'Borrower', '25571'.random_int(1000000, 9999999), $income);

        return LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-SEQ-'.random_int(1000, 9999),
            'requested_amount' => $amount,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);
    }

    private function person(string $first, string $last, string $phone, float $income): Customer
    {
        return Customer::create([
            'customer_number' => 'CU-SEQ-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $phone,
            'monthly_income' => $income,
        ]);
    }

    private function keyStatements(LoanApplication $application, float $total): void
    {
        $payload = $application->screening_payload ?? [];
        $payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.income_evidence'] = [
            'verdict' => 'fail',
            'fail_reason_code' => 'revenue_mismatch',
            'source' => 'system',
            'statement_deposits_total' => $total,
            'statement_months' => 6,
            'statement_monthly' => round($total / 6, 2),
        ];
        $application->update(['screening_payload' => $payload]);
    }
}
