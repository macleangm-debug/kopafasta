<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\ApplicationBorrowerStatusService;
use App\Services\ApplicationIntakeReadinessService;
use App\Services\ApplicationIntakeReconciliationService;
use App\Services\ApplicationIntakeTransitionService;
use App\Services\CapacityAutoRejectService;
use App\Services\CreditEligibilityPolicyService;
use App\Services\GuarantorInvitationService;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ApplicationIntakeFirstGateFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawn_application_does_not_display_awaiting_guarantor(): void
    {
        [, , $application] = $this->application(status: 'withdrawn', stage: 'awaiting_guarantor');

        $readiness = app(ApplicationIntakeReadinessService::class);
        $this->assertSame('withdrawn', $readiness->displayStatus($application));
        $this->assertSame('withdrawn', $readiness->displayStage($application));
        $this->assertSame('withdrawn', $readiness->resolve($application)['state']);
        $this->assertSame('withdrawn', app(ApplicationBorrowerStatusService::class)->forApplication($application)['code']);
    }

    public function test_failed_first_gate_does_not_invite_guarantor(): void
    {
        [$borrower, , $application] = $this->application();
        GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $application->loan_product_id,
            'type' => 'external',
            'status' => 'pending',
            'contact' => '255700000001',
            'invitee_name' => 'Nominated',
            'token' => 'tok-fail-'.random_int(1000, 9999),
        ]);

        $this->mock(CreditEligibilityPolicyService::class, function ($mock): void {
            $mock->shouldReceive('evaluate')->andReturn([
                'application_action' => CreditEligibilityPolicyService::ACTION_PENDING_REJECTION,
                'reason' => 'Borrower failed initial affordability. A guarantor cannot rescue this loan.',
                'participants' => [],
            ]);
        });

        $fresh = app(ApplicationIntakeTransitionService::class)->afterBorrowerSubmit($application->fresh(['customer', 'product']));
        $this->assertSame('submitted', $fresh->status);
        $this->assertSame('initial_decision_hold', $fresh->current_stage);
        $this->assertSame(CapacityAutoRejectService::STATUS_PENDING, data_get($fresh->screening_payload, 'capacity_auto_reject.status'));
        $this->assertNotEmpty(data_get($fresh->screening_payload, 'capacity_auto_reject.parked_at'));
        $this->assertNotEmpty(data_get($fresh->screening_payload, 'capacity_auto_reject.auto_reject_at'));
        $this->assertSame(
            'underwriting.capacity_auto_reject_delay_hours',
            data_get($fresh->screening_payload, 'capacity_auto_reject.settings_key'),
        );
        $this->assertSame(
            data_get($fresh->screening_payload, 'capacity_auto_reject.auto_reject_at'),
            data_get($fresh->screening_payload, 'intake.initial_gate.feedback_release_at'),
        );
        $this->assertNull(data_get($fresh->screening_payload, 'intake.guarantor_invited_at'));
        $this->assertSame(0, NotificationLog::query()->where('template', 'guarantor_request')->count());
        $this->assertSame(0, NotificationLog::query()->where('template', 'guarantor_sent')->count());
        $this->assertSame(0, NotificationLog::query()->where('template', 'application_rejected')->count());

        app(ApplicationIntakeTransitionService::class)->dispatchGuarantorInvitation($fresh->fresh());
        $this->assertNull(data_get($fresh->fresh()->screening_payload, 'intake.guarantor_invited_at'));
    }

    public function test_failed_first_gate_reuses_capacity_park_fire_after_settings_hours(): void
    {
        Setting::set('underwriting.enable_capacity_auto_reject', true);
        Setting::set('underwriting.capacity_auto_reject_delay_hours', 12);

        [, , $application] = $this->application();
        $this->mock(CreditEligibilityPolicyService::class, function ($mock): void {
            $mock->shouldReceive('evaluate')->andReturn([
                'application_action' => CreditEligibilityPolicyService::ACTION_PENDING_REJECTION,
                'reason' => 'Borrower failed initial affordability. A guarantor cannot rescue this loan.',
                'participants' => [],
            ]);
        });

        $fresh = app(ApplicationIntakeTransitionService::class)->afterBorrowerSubmit($application->fresh(['customer', 'product']));
        $this->assertSame('submitted', $fresh->status);
        $this->assertSame(CapacityAutoRejectService::STATUS_PENDING, data_get($fresh->screening_payload, 'capacity_auto_reject.status'));

        Carbon::setTestNow(now()->addHours(13));
        $fired = app(CapacityAutoRejectService::class)->fireDue();
        Carbon::setTestNow();

        $this->assertCount(1, $fired);
        $this->assertSame('rejected', $application->fresh()->status);
        $this->assertSame(CapacityAutoRejectService::STATUS_FIRED, data_get($application->fresh()->screening_payload, 'capacity_auto_reject.status'));
        $this->assertNull(data_get($application->fresh()->screening_payload, 'intake.guarantor_invited_at'));
    }

    public function test_passed_first_gate_invites_nominated_guarantor_once(): void
    {
        [$borrower, , $application] = $this->application();
        GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $application->loan_product_id,
            'type' => 'external',
            'status' => 'pending',
            'contact' => '255700000002',
            'invitee_name' => 'Nominated',
            'token' => 'tok-pass-'.random_int(1000, 9999),
        ]);

        $this->mock(CreditEligibilityPolicyService::class, function ($mock): void {
            $mock->shouldReceive('evaluate')->andReturn([
                'application_action' => 'continue',
                'reason' => '',
                'participants' => [],
            ]);
        });

        $service = app(ApplicationIntakeTransitionService::class);
        $fresh = $service->afterBorrowerSubmit($application->fresh(['customer', 'product']));
        $this->assertSame('awaiting_guarantor', $fresh->status);
        $this->assertNotNull(data_get($fresh->screening_payload, 'intake.guarantor_invited_at'));

        $service->dispatchGuarantorInvitation($fresh->fresh());
        $this->assertSame(data_get($fresh->screening_payload, 'intake.guarantor_invited_at'), data_get($fresh->fresh()->screening_payload, 'intake.guarantor_invited_at'));
    }

    public function test_guarantor_completion_moves_to_ready_for_screening_not_screening(): void
    {
        [, , $application] = $this->application(status: 'awaiting_guarantor', stage: 'awaiting_guarantor', withApprovedGuarantor: true);

        $this->mock(ProfileCompletionService::class, function ($mock): void {
            $mock->shouldReceive('isFullyComplete')->andReturn(true);
            $mock->shouldReceive('completionSummary')->andReturn([
                'percent' => 100,
                'remaining' => [],
                'actionable' => [],
            ]);
        });

        $this->assertTrue(app(GuarantorInvitationService::class)->tryReleaseApplicationFromGuarantorHold($application->fresh()));
        $fresh = $application->fresh();
        $this->assertSame('submitted', $fresh->status);
        $this->assertSame('ready_for_screening', $fresh->current_stage);
    }

    public function test_reconciliation_dry_run_identifies_steward_like_stranded_draft(): void
    {
        [, , $application] = $this->application(
            status: 'draft',
            stage: 'draft',
            submitted: true,
            withApprovedGuarantor: true,
            number: 'APP-IL-LQU6',
        );

        $this->mock(ProfileCompletionService::class, function ($mock): void {
            $mock->shouldReceive('isFullyComplete')->andReturn(true);
            $mock->shouldReceive('completionSummary')->andReturn([
                'percent' => 100,
                'remaining' => [],
                'actionable' => [],
            ]);
        });
        $this->mock(GuarantorInvitationService::class, function ($mock): void {
            $mock->shouldReceive('hasReadyGuarantor')->andReturn(true);
            $mock->shouldReceive('guarantorHoldBlocker')->andReturn(null);
        });

        $plan = app(ApplicationIntakeReconciliationService::class)->plan(['APP-IL-LQU6']);
        $this->assertNotEmpty($plan);
        $this->assertSame('APP-IL-LQU6', $plan[0]['application_number']);
        $this->assertSame('transition', $plan[0]['action']);
        $this->assertSame('ready_for_screening', $plan[0]['to_stage']);
        $this->assertSame('draft', $application->fresh()->status);
    }

    public function test_closed_awaiting_stage_is_cleared_in_reconciliation_plan(): void
    {
        [, , $application] = $this->application(status: 'withdrawn', stage: 'awaiting_guarantor', number: 'APP-EM-MU8Q');

        $plan = app(ApplicationIntakeReconciliationService::class)->plan(['APP-EM-MU8Q']);
        $this->assertSame('clear_closed_stage', $plan[0]['action']);
        $this->assertSame('withdrawn', $plan[0]['to_status']);
        $this->assertSame('awaiting_guarantor', $application->fresh()->current_stage);
    }

    /**
     * @return array{0: Customer, 1: LoanProduct, 2: LoanApplication}
     */
    private function application(
        string $status = 'submitted',
        string $stage = 'submitted_initial_check',
        bool $submitted = true,
        bool $withApprovedGuarantor = false,
        ?string $number = null,
    ): array {
        $branch = Branch::create([
            'code' => 'INT'.random_int(10, 99),
            'name' => 'Intake Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'code' => 'INT-'.random_int(100, 999),
            'name' => 'Intake Product',
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
            'customer_number' => 'CU-INT-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Steward',
            'last_name' => 'Intake',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $branch->id,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => $number ?: 'APP-INT-'.random_int(1000, 9999),
            'requested_amount' => 500000,
            'requested_tenure_months' => 6,
            'status' => $status,
            'current_stage' => $stage,
            'submitted_at' => $submitted ? now() : null,
        ]);

        if ($withApprovedGuarantor) {
            $guarantorCustomer = Customer::create([
                'user_id' => User::factory()->create(['role' => 'borrower'])->id,
                'customer_number' => 'CU-INT-G-'.random_int(1000, 9999),
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'Kasimu',
                'last_name' => 'Intake',
                'phone' => '25576'.random_int(1000000, 9999999),
                'branch_id' => $branch->id,
            ]);
            $contact = Guarantor::create([
                'first_name' => 'Kasimu',
                'last_name' => 'Intake',
                'phone' => $guarantorCustomer->phone,
                'relationship' => 'sibling',
            ]);
            $link = CustomerGuarantor::create([
                'customer_id' => $borrower->id,
                'guarantor_id' => $contact->id,
                'loan_application_id' => $application->id,
                'status' => 'approved',
            ]);
            GuarantorInvitation::create([
                'customer_id' => $borrower->id,
                'customer_guarantor_id' => $link->id,
                'loan_application_id' => $application->id,
                'loan_product_id' => $product->id,
                'guarantor_customer_id' => $guarantorCustomer->id,
                'type' => 'external',
                'status' => 'accepted',
                'contact' => $guarantorCustomer->phone,
                'invitee_name' => 'Kasimu Intake',
                'token' => 'tok-int-'.random_int(10000, 99999),
            ]);
        }

        return [$borrower, $product, $application];
    }
}
