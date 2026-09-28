<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\ApplicationIntakeReadinessService;
use App\Services\ApplicationIntakeReconciliationService;
use App\Services\CapacityAutoRejectService;
use App\Services\CreditEligibilityPolicyService;
use App\Services\GuarantorInvitationService;
use App\Services\LoanApplicationDraftService;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationIntakeReconciliationAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('underwriting.enable_capacity_auto_reject', true);
        Setting::set('underwriting.capacity_auto_reject_delay_hours', 12);
    }

    public function test_awaiting_guarantor_gate_one_fail_parks_and_does_not_invite(): void
    {
        [, , $application] = $this->application(status: 'awaiting_guarantor', stage: 'awaiting_guarantor', nominated: true);
        $this->failGate();
        $this->completeProfile();

        $plan = app(ApplicationIntakeReconciliationService::class)->plan([$application->application_number]);
        $this->assertSame('park', $plan[0]['action']);
        $this->assertSame('initial_decision_hold', $plan[0]['to_stage']);

        app(ApplicationIntakeReconciliationService::class)->apply([$application->application_number], notify: false);
        $fresh = $application->fresh();
        $this->assertSame('submitted', $fresh->status);
        $this->assertSame('initial_decision_hold', $fresh->current_stage);
        $this->assertSame(CapacityAutoRejectService::STATUS_PENDING, data_get($fresh->screening_payload, 'capacity_auto_reject.status'));
        $this->assertNull(data_get($fresh->screening_payload, 'intake.guarantor_invited_at'));
        $this->assertSame(0, NotificationLog::query()->where('template', 'guarantor_sent')->count());
        $this->assertNotSame('screening', $fresh->current_stage);
    }

    public function test_pass_plus_incomplete_guarantor_stays_awaiting(): void
    {
        [, , $application] = $this->application(status: 'awaiting_guarantor', stage: 'awaiting_guarantor', nominated: true);
        $this->passGate();
        $this->completeProfile();

        $plan = app(ApplicationIntakeReconciliationService::class)->plan([$application->application_number]);
        $this->assertSame('none', $plan[0]['action']);
        $this->assertSame('awaiting_guarantor', $application->fresh()->status);
    }

    public function test_pass_plus_completed_guarantor_goes_ready_not_screening(): void
    {
        [, , $application] = $this->application(status: 'awaiting_guarantor', stage: 'awaiting_guarantor', nominated: true, readyGuarantor: true);
        $this->passGate();
        $this->completeProfile();

        $plan = app(ApplicationIntakeReconciliationService::class)->plan([$application->application_number]);
        $this->assertSame('transition', $plan[0]['action']);
        $this->assertSame('ready_for_screening', $plan[0]['to_stage']);

        app(ApplicationIntakeReconciliationService::class)->apply([$application->application_number]);
        $fresh = $application->fresh();
        $this->assertSame('submitted', $fresh->status);
        $this->assertSame('ready_for_screening', $fresh->current_stage);
    }

    public function test_zr93_type_incomplete_returns_to_incomplete_applications(): void
    {
        $this->assertIncompleteTypeReturnsToWorkspace('APP-IL-ZR93', 84, ['NIDA front', 'NIDA back', 'Income proof']);
    }

    public function test_84sh_type_incomplete_returns_to_incomplete_applications(): void
    {
        $this->assertIncompleteTypeReturnsToWorkspace('APP-IL-84SH', 89, ['Face photo', 'Residence letter']);
    }

    public function test_historical_submitted_at_does_not_override_incomplete_readiness(): void
    {
        [, , $application] = $this->application(
            status: 'draft',
            stage: 'draft',
            nominated: true,
            number: 'APP-IL-ZR93',
        );
        $this->incompleteProfile(84, ['NIDA front']);
        $this->gateMustNotRun();

        $resolved = app(ApplicationIntakeReadinessService::class)->resolve($application->fresh(['customer', 'product']));
        $this->assertSame(ApplicationIntakeReadinessService::STATE_DRAFT, $resolved['state']);
        $this->assertFalse($resolved['borrower_submitted']);
        $this->assertSame('not_run', $resolved['initial_gate']['result'] ?? null);
        $this->assertNotSame('awaiting_guarantor', $resolved['state']);
        $this->assertNotSame('initial_decision_hold', $resolved['state']);
    }

    public function test_submitted_draft_with_incomplete_profile_remains_draft(): void
    {
        [, , $application] = $this->application(status: 'draft', stage: 'draft', nominated: true);
        $this->incompleteProfile(80, ['Face photo']);
        $this->gateMustNotRun();

        $plan = app(ApplicationIntakeReconciliationService::class)->plan([$application->application_number]);
        $this->assertSame('return_to_incomplete', $plan[0]['action']);
        $this->assertSame('draft', $plan[0]['to_status']);
        $this->assertNull($plan[0]['gate_one_action']);

        app(ApplicationIntakeReconciliationService::class)->apply([$application->application_number]);
        $fresh = $application->fresh();
        $this->assertSame('draft', $fresh->status);
        $this->assertSame('draft', $fresh->current_stage);
        $this->assertNotNull($fresh->submitted_at);
        $this->assertNull(data_get($fresh->screening_payload, 'capacity_auto_reject.status'));
        $this->assertNotSame('awaiting_guarantor', $fresh->status);
        $this->assertNotSame('screening', $fresh->current_stage);
        $this->assertSame(1, GuarantorInvitation::query()->where('loan_application_id', $fresh->id)->count());
    }

    public function test_submitted_draft_valid_fail_parks(): void
    {
        [, , $application] = $this->application(status: 'draft', stage: 'draft');
        $this->failGate();
        $this->completeProfile();

        $plan = app(ApplicationIntakeReconciliationService::class)->plan([$application->application_number]);
        $this->assertSame('park', $plan[0]['action']);

        app(ApplicationIntakeReconciliationService::class)->apply([$application->application_number]);
        $this->assertSame('initial_decision_hold', $application->fresh()->current_stage);
        $this->assertSame(CapacityAutoRejectService::STATUS_PENDING, data_get($application->fresh()->screening_payload, 'capacity_auto_reject.status'));
    }

    public function test_submitted_draft_pass_with_outstanding_guarantor_awaits(): void
    {
        [, , $application] = $this->application(status: 'draft', stage: 'draft', nominated: true);
        $this->passGate();
        $this->completeProfile();

        $plan = app(ApplicationIntakeReconciliationService::class)->plan([$application->application_number]);
        $this->assertSame('transition', $plan[0]['action']);
        $this->assertSame('awaiting_guarantor', $plan[0]['to_status']);
    }

    public function test_submitted_draft_pass_without_outstanding_guarantor_is_ready(): void
    {
        [, , $application] = $this->application(status: 'draft', stage: 'draft', nominated: true, readyGuarantor: true);
        $this->passGate();
        $this->completeProfile();

        $plan = app(ApplicationIntakeReconciliationService::class)->plan([$application->application_number]);
        $this->assertSame('ready_for_screening', $plan[0]['to_stage']);
        app(ApplicationIntakeReconciliationService::class)->apply([$application->application_number]);
        $this->assertSame('ready_for_screening', $application->fresh()->current_stage);
    }

    public function test_withdrawn_stale_stage_is_normalized_without_gate(): void
    {
        [, , $application] = $this->application(status: 'withdrawn', stage: 'awaiting_guarantor');

        $plan = app(ApplicationIntakeReconciliationService::class)->plan([$application->application_number]);
        $this->assertSame('clear_closed_stage', $plan[0]['action']);
        app(ApplicationIntakeReconciliationService::class)->apply([$application->application_number]);
        $fresh = $application->fresh();
        $this->assertSame('withdrawn', $fresh->status);
        $this->assertSame('withdrawn', $fresh->current_stage);
    }

    public function test_second_run_does_not_repark_or_reinvite(): void
    {
        [, , $application] = $this->application(status: 'awaiting_guarantor', stage: 'awaiting_guarantor', nominated: true);
        $this->failGate();
        $this->completeProfile();

        $service = app(ApplicationIntakeReconciliationService::class);
        $service->apply([$application->application_number]);
        $firstParkedAt = data_get($application->fresh()->screening_payload, 'capacity_auto_reject.parked_at');
        $firstUntil = data_get($application->fresh()->screening_payload, 'capacity_auto_reject.auto_reject_at');

        $again = $service->plan([$application->application_number]);
        $this->assertSame('none', $again[0]['action']);
        $service->apply([$application->application_number]);

        $fresh = $application->fresh();
        $this->assertSame($firstParkedAt, data_get($fresh->screening_payload, 'capacity_auto_reject.parked_at'));
        $this->assertSame($firstUntil, data_get($fresh->screening_payload, 'capacity_auto_reject.auto_reject_at'));
        $this->assertSame(0, NotificationLog::query()->where('template', 'guarantor_sent')->count());
        $this->assertNotSame('screening', $fresh->current_stage);
    }

    /**
     * @param  list<string>  $gaps
     */
    private function assertIncompleteTypeReturnsToWorkspace(string $number, int $percent, array $gaps): void
    {
        [, , $application] = $this->application(
            status: 'draft',
            stage: 'draft',
            nominated: true,
            number: $number,
        );
        $invitationId = GuarantorInvitation::query()->where('loan_application_id', $application->id)->value('id');
        $this->incompleteProfile($percent, $gaps);
        $this->gateMustNotRun();

        $service = app(ApplicationIntakeReconciliationService::class);
        $plan = $service->plan([$number]);
        $this->assertSame('return_to_incomplete', $plan[0]['action']);
        $this->assertNull($plan[0]['gate_one_action']);

        $service->apply([$number]);
        $fresh = $application->fresh();
        $this->assertSame('draft', $fresh->status);
        $this->assertSame('draft', $fresh->current_stage);
        $this->assertNotNull($fresh->submitted_at);
        $this->assertNull(data_get($fresh->screening_payload, 'capacity_auto_reject.status'));
        $this->assertNotSame('awaiting_guarantor', $fresh->status);
        $this->assertNotSame('submitted', $fresh->status);
        $this->assertNotSame('ready_for_screening', $fresh->current_stage);
        $this->assertNotSame('screening', $fresh->current_stage);
        $this->assertNotSame('initial_decision_hold', $fresh->current_stage);

        $this->assertTrue(app(LoanApplicationDraftService::class)->hasAlignedIncompleteDraft($fresh));
        $this->assertSame(1, app(LoanApplicationDraftService::class)->countIncomplete());
        $this->assertSame(
            0,
            LoanApplication::query()
                ->where('application_number', $number)
                ->whereNotIn('status', LoanApplication::PRE_SUBMIT_STATUSES)
                ->count(),
        );
        $this->assertSame($invitationId, GuarantorInvitation::query()->where('loan_application_id', $fresh->id)->value('id'));

        $resolved = app(ApplicationIntakeReadinessService::class)->resolve($fresh->fresh(['customer', 'product']));
        $this->assertSame(ApplicationIntakeReadinessService::STATE_DRAFT, $resolved['state']);
        $this->assertSame('not_run', $resolved['initial_gate']['result'] ?? null);

        $again = $service->plan([$number]);
        $this->assertSame('none', $again[0]['action']);
        $service->apply([$number]);
        $this->assertSame($invitationId, GuarantorInvitation::query()->where('loan_application_id', $fresh->id)->value('id'));
        $this->assertSame(1, LoanApplicationDraft::query()->where('draft_reference', $number)->count());
        $this->assertSame('draft', $application->fresh()->status);
    }

    /**
     * @param  list<string>  $gaps
     */
    private function incompleteProfile(int $percent, array $gaps): void
    {
        $this->mock(ProfileCompletionService::class, function ($mock) use ($percent, $gaps): void {
            $mock->shouldReceive('isFullyComplete')->andReturn(false);
            $mock->shouldReceive('completionSummary')->andReturn([
                'percent' => $percent,
                'remaining' => $gaps,
                'actionable' => collect($gaps)->map(fn (string $label) => ['label' => $label])->all(),
            ]);
            $mock->shouldReceive('calculate')->andReturn([
                'percent' => $percent,
                'sections' => [],
            ]);
        });
    }

    private function gateMustNotRun(): void
    {
        $this->mock(CreditEligibilityPolicyService::class, function ($mock): void {
            $mock->shouldNotReceive('evaluate');
        });
    }

    private function failGate(): void
    {
        $this->mock(CreditEligibilityPolicyService::class, function ($mock): void {
            $mock->shouldReceive('evaluate')->andReturn([
                'application_action' => CreditEligibilityPolicyService::ACTION_PENDING_REJECTION,
                'reason' => 'Borrower failed initial affordability. A guarantor cannot rescue this loan.',
                'participants' => [],
            ]);
        });
    }

    private function passGate(): void
    {
        $this->mock(CreditEligibilityPolicyService::class, function ($mock): void {
            $mock->shouldReceive('evaluate')->andReturn([
                'application_action' => CreditEligibilityPolicyService::ACTION_CONTINUE,
                'reason' => 'Eligible to continue screening.',
                'participants' => [],
            ]);
        });
    }

    private function completeProfile(): void
    {
        $this->mock(ProfileCompletionService::class, function ($mock): void {
            $mock->shouldReceive('isFullyComplete')->andReturn(true);
            $mock->shouldReceive('completionSummary')->andReturn([
                'percent' => 100,
                'remaining' => [],
                'actionable' => [],
            ]);
        });
    }

    /**
     * @return array{0: Customer, 1: LoanProduct, 2: LoanApplication}
     */
    private function application(
        string $status = 'submitted',
        string $stage = 'submitted_initial_check',
        bool $nominated = false,
        bool $readyGuarantor = false,
        ?string $number = null,
    ): array {
        $branch = Branch::create([
            'code' => 'RAL'.random_int(10, 99),
            'name' => 'Align Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'code' => 'RAL-'.random_int(100, 999),
            'name' => 'Align Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100000,
            'max_amount' => 5000000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
        ]);
        $borrower = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-RAL-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Align',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $branch->id,
            'monthly_income' => 800000,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => $number ?? ('APP-RAL-'.random_int(1000, 9999)),
            'requested_amount' => 500000,
            'requested_tenure_months' => 6,
            'status' => $status,
            'current_stage' => $stage,
            'submitted_at' => now(),
        ]);

        if ($nominated) {
            $this->mock(GuarantorInvitationService::class, function ($mock) use ($readyGuarantor): void {
                $mock->shouldReceive('hasReadyGuarantor')->andReturn($readyGuarantor);
                $mock->shouldReceive('guarantorHoldBlocker')->andReturn($readyGuarantor ? null : 'profile_incomplete');
            });
            $contact = Guarantor::create([
                'first_name' => 'G',
                'last_name' => 'Align',
                'phone' => '25576'.random_int(1000000, 9999999),
                'relationship' => 'sibling',
            ]);
            $link = CustomerGuarantor::create([
                'customer_id' => $borrower->id,
                'guarantor_id' => $contact->id,
                'loan_application_id' => $application->id,
                'status' => $readyGuarantor ? 'approved' : 'pending',
            ]);
            GuarantorInvitation::create([
                'customer_id' => $borrower->id,
                'customer_guarantor_id' => $link->id,
                'loan_application_id' => $application->id,
                'loan_product_id' => $product->id,
                'type' => 'external',
                'status' => $readyGuarantor ? 'accepted' : 'pending',
                'contact' => $contact->phone,
                'invitee_name' => 'G Align',
                'token' => 'tok-ral-'.random_int(10000, 99999),
            ]);
        }

        return [$borrower, $product, $application];
    }
}
