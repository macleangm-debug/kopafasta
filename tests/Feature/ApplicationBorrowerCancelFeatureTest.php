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
use App\Models\ApplicationStageHistory;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\ApplicationIntakeReadinessService;
use App\Services\ApplicationIntakeReconciliationService;
use App\Services\ApplicationIntakeTransitionService;
use App\Services\CapacityAutoRejectService;
use App\Services\CreditEligibilityPolicyService;
use App\Services\LoanApplicationDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationBorrowerCancelFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('underwriting.enable_capacity_auto_reject', true);
        Setting::set('underwriting.capacity_auto_reject_delay_hours', 12);
    }

    public function test_incomplete_draft_can_cancel_with_confirmation_copy(): void
    {
        [$user, $application, $draft, $invitation] = $this->draftCase();
        $this->gateMustNotRun();

        $this->actingAs($user)
            ->get(route('site.borrower.loan-profile.draft', $draft))
            ->assertOk()
            ->assertSee(__('borrower.policy.cancel_application'), false)
            ->assertSee(__('borrower.policy.cancel_application_confirm_title'), false)
            ->assertSee(__('borrower.policy.cancel_application_confirm_body'), false)
            ->assertSee(__('borrower.policy.cancel_application_keep'), false)
            ->assertSee('confirmForm', false);

        $this->actingAs($user)
            ->post(route('site.borrower.application.withdraw', $application))
            ->assertRedirect(route('site.borrower.loans', ['tab' => 'applications']));

        $fresh = $application->fresh();
        $this->assertSame('withdrawn', $fresh->status);
        $this->assertSame('withdrawn', $fresh->current_stage);
        $this->assertNull($fresh->rejection_reason);
        $this->assertSame($application->application_number, $fresh->application_number);
        $this->assertSame($invitation->id, GuarantorInvitation::query()->where('loan_application_id', $fresh->id)->value('id'));
        $this->assertSame(0, NotificationLog::query()->where('template', 'guarantor_sent')->count());
        $this->assertSame(0, app(LoanApplicationDraftService::class)->countIncomplete());
        $this->assertFalse(app(LoanApplicationDraftService::class)->hasAlignedIncompleteDraft($fresh));
    }

    public function test_cancelled_draft_does_not_run_first_gate_or_release_guarantor(): void
    {
        [$user, $application, $draft, $invitation] = $this->draftCase();
        $this->gateMustNotRun();

        $this->actingAs($user)->post(route('site.borrower.draft.discard', $draft));

        $fresh = $application->fresh();
        $this->assertSame('withdrawn', $fresh->status);
        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertNull(data_get($fresh->screening_payload, 'intake.guarantor_invited_at'));
        $this->assertNull(data_get($fresh->screening_payload, 'intake.initial_gate.result'));
    }

    public function test_admin_can_restore_mistaken_incomplete_cancel_without_first_gate(): void
    {
        [$user, $application, $draft] = $this->draftCase();
        $number = $application->application_number;
        $this->actingAs($user)->post(route('site.borrower.application.withdraw', $application));
        $withdrawn = $application->fresh();
        $this->assertTrue(app(ApplicationIntakeTransitionService::class)->canRestoreIncompleteBorrowerCancel($withdrawn));

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', ['customer' => $withdrawn->customer_id, 'tab' => 'applications']))
            ->assertOk()
            ->assertSee(__('admin.intake.restore_application'), false)
            ->assertSee(__('admin.intake.restore_incomplete_confirm', ['number' => $number]), false);

        $this->gateMustNotRun();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.restore-incomplete-cancel', $withdrawn), [
                'confirmed' => '1',
                'reason' => 'Borrower cancelled the draft by mistake.',
            ])
            ->assertRedirect();

        $restored = $application->fresh();
        $this->assertSame('draft', $restored->status);
        $this->assertSame('draft', $restored->current_stage);
        $this->assertSame($number, $restored->application_number);
        $this->assertNotNull(data_get($restored->screening_payload, 'intake.cancelled_by_borrower_at'));
        $this->assertNotNull(data_get($restored->screening_payload, 'intake.restored_from_borrower_cancel_at'));
        $this->assertTrue(app(LoanApplicationDraftService::class)->hasAlignedIncompleteDraft($restored));
        $this->assertTrue(
            ApplicationStageHistory::query()
                ->where('loan_application_id', $restored->id)
                ->where('remarks', 'like', 'Borrower cancelled an incomplete application%')
                ->exists()
        );
        $this->assertTrue(
            ApplicationStageHistory::query()
                ->where('loan_application_id', $restored->id)
                ->where('remarks', 'like', 'Admin restored a mistaken incomplete cancellation%')
                ->exists()
        );
        $this->assertFalse(app(ApplicationIntakeTransitionService::class)->canRestoreIncompleteBorrowerCancel($restored));
    }

    public function test_submitted_withdrawal_cannot_be_restored(): void
    {
        [, $application] = $this->openCase(status: 'withdrawn', stage: 'withdrawn');
        $application->update([
            'screening_payload' => [
                'intake' => [
                    'submitted_at' => now()->toIso8601String(),
                    'cancelled_from_status' => 'submitted',
                    'cancelled_from_stage' => 'submitted',
                ],
            ],
        ]);

        $this->assertFalse(app(ApplicationIntakeTransitionService::class)->canRestoreIncompleteBorrowerCancel($application->fresh()));

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', ['customer' => $application->customer_id, 'tab' => 'applications']))
            ->assertOk()
            ->assertDontSee(__('admin.intake.restore_application'), false);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.customers.show', $application->customer_id))
            ->post(route('admin.loan-applications.restore-incomplete-cancel', $application), [
                'confirmed' => '1',
                'reason' => 'Trying to reopen a submitted withdrawal.',
            ])
            ->assertSessionHasErrors('reason');
        $this->assertSame('withdrawn', $application->fresh()->status);
    }

    public function test_submitted_borrower_cannot_withdraw_via_endpoint(): void
    {
        $this->assertWithdrawDenied($this->openCase(status: 'submitted', stage: 'submitted_initial_check'));
    }

    public function test_awaiting_guarantor_cannot_withdraw(): void
    {
        $this->assertWithdrawDenied($this->openCase(status: 'awaiting_guarantor', stage: 'awaiting_guarantor'));
    }

    public function test_parked_screening_cannot_withdraw(): void
    {
        [$user, $application] = $this->openCase(status: 'submitted', stage: 'initial_decision_hold');
        $application->update([
            'screening_payload' => [
                'capacity_auto_reject' => ['status' => CapacityAutoRejectService::STATUS_PENDING],
                'intake' => ['initial_gate' => ['result' => 'failed']],
            ],
        ]);
        $this->assertWithdrawDenied([$user, $application->fresh()]);
    }

    public function test_ready_for_screening_cannot_withdraw(): void
    {
        $this->assertWithdrawDenied($this->openCase(status: 'submitted', stage: 'ready_for_screening'));
    }

    public function test_screening_cannot_withdraw(): void
    {
        $this->assertWithdrawDenied($this->openCase(status: 'submitted', stage: 'screening'));
    }

    public function test_later_lending_stages_cannot_withdraw(): void
    {
        $this->assertWithdrawDenied($this->openCase(status: 'under_review', stage: 'pre_approval'));
        $this->assertWithdrawDenied($this->openCase(status: 'approved', stage: 'approval'));
    }

    public function test_historical_withdrawn_record_is_preserved(): void
    {
        [$user, $application] = $this->openCase(
            status: 'withdrawn',
            stage: 'awaiting_guarantor',
            number: 'APP-EM-MU8Q',
        );

        $plan = app(ApplicationIntakeReconciliationService::class)->plan(['APP-EM-MU8Q']);
        $this->assertSame('clear_closed_stage', $plan[0]['action']);
        app(ApplicationIntakeReconciliationService::class)->apply(['APP-EM-MU8Q']);

        $fresh = $application->fresh();
        $this->assertSame('withdrawn', $fresh->status);
        $this->assertSame('withdrawn', $fresh->current_stage);

        $this->actingAs($user)
            ->post(route('site.borrower.application.withdraw', $fresh))
            ->assertRedirect();
        $this->assertSame('withdrawn', $application->fresh()->status);
    }

    public function test_cancel_copy_is_english_and_swahili(): void
    {
        [, , $draft] = $this->draftCase();
        $user = User::query()->find($draft->customer->user_id);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.loan-profile.draft', $draft))
            ->assertSee('Cancel application', false)
            ->assertSee('Keep application', false);

        $this->actingAs($user)
            ->withSession(['locale' => 'sw'])
            ->get(route('site.borrower.loan-profile.draft', $draft))
            ->assertSee('Ghairi ombi', false)
            ->assertSee('Hifadhi ombi', false);
    }

    /**
     * @param  array{0: User, 1: LoanApplication}  $case
     */
    private function assertWithdrawDenied(array $case): void
    {
        [$user, $application] = $case;
        $before = $application->only(['status', 'current_stage']);

        $this->actingAs($user)
            ->from(route('site.borrower.loans', ['tab' => 'applications']))
            ->post(route('site.borrower.application.withdraw', $application))
            ->assertRedirect();

        $fresh = $application->fresh();
        $this->assertSame($before['status'], $fresh->status);
        $this->assertSame($before['current_stage'], $fresh->current_stage);
        $this->assertFalse(app(ApplicationIntakeReadinessService::class)->borrowerMayCancel($fresh));
    }

    private function gateMustNotRun(): void
    {
        $this->mock(CreditEligibilityPolicyService::class, function ($mock): void {
            $mock->shouldNotReceive('evaluate');
        });
    }

    /**
     * @return array{0: User, 1: LoanApplication, 2: LoanApplicationDraft, 3: GuarantorInvitation}
     */
    private function draftCase(): array
    {
        [$user, $application] = $this->openCase(status: 'draft', stage: 'draft', nominated: true);
        $draft = LoanApplicationDraft::create([
            'customer_id' => $application->customer_id,
            'loan_product_id' => $application->loan_product_id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => $application->application_number,
            'saved_at' => now(),
            'payload' => [
                'application_started' => true,
                'form' => [
                    'loan_product_id' => $application->loan_product_id,
                    'requested_amount' => 500000,
                ],
            ],
        ]);
        $invitation = GuarantorInvitation::query()->where('loan_application_id', $application->id)->firstOrFail();

        return [$user, $application, $draft, $invitation];
    }

    /**
     * @return array{0: User, 1: LoanApplication}
     */
    private function openCase(
        string $status,
        string $stage,
        bool $nominated = false,
        ?string $number = null,
    ): array {
        $branch = Branch::create([
            'code' => 'CXL'.random_int(10, 99),
            'name' => 'Cancel Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'code' => 'CXL-'.random_int(100, 999),
            'name' => 'Cancel Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100000,
            'max_amount' => 5000000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
        ]);
        $user = User::factory()->create(['role' => 'borrower']);
        $borrower = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-CXL-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Cancel',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $branch->id,
            'monthly_income' => 800000,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => $number ?? ('APP-CXL-'.random_int(1000, 9999)),
            'requested_amount' => 500000,
            'requested_tenure_months' => 6,
            'status' => $status,
            'current_stage' => $stage,
            'submitted_at' => $status === 'draft' ? now() : now(),
        ]);

        if ($nominated) {
            $contact = Guarantor::create([
                'first_name' => 'G',
                'last_name' => 'Cancel',
                'phone' => '25576'.random_int(1000000, 9999999),
                'relationship' => 'sibling',
            ]);
            $link = CustomerGuarantor::create([
                'customer_id' => $borrower->id,
                'guarantor_id' => $contact->id,
                'loan_application_id' => $application->id,
                'status' => 'pending',
            ]);
            GuarantorInvitation::create([
                'customer_id' => $borrower->id,
                'customer_guarantor_id' => $link->id,
                'loan_application_id' => $application->id,
                'loan_product_id' => $product->id,
                'type' => 'external',
                'status' => 'pending',
                'contact' => $contact->phone,
                'invitee_name' => 'G Cancel',
                'token' => 'tok-cxl-'.random_int(10000, 99999),
            ]);
        }

        return [$user, $application];
    }
}
