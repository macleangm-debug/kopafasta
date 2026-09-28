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
use App\Models\User;
use App\Services\ApplicationIntakeReadinessService;
use App\Services\CapacityAutoRejectService;
use App\Services\LoanApplicationDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationDashboardQueueAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_applications_dashboard_count_matches_system_sorted_destination(): void
    {
        $admin = $this->staff();
        $this->parked('APP-IL-FF24');
        $this->parked('APP-IL-UZ16');
        $this->parked('APP-IL-PENA');
        $this->parked('APP-WL-VQWH');
        $this->ready('APP-IL-LQU6');
        $this->awaitingReplacement('APP-IL-RZNT');
        $this->incomplete('APP-IL-ZR93');
        $this->incomplete('APP-IL-84SH');
        $this->withdrawn('APP-EM-MU8Q');
        $this->activeScreening('APP-IL-SCRN');

        $readiness = app(ApplicationIntakeReadinessService::class);
        $this->assertSame(6, $readiness->systemSortedCount());
        $this->assertSame(4, $readiness->systemSortedCount('parked'));
        $this->assertSame(1, $readiness->systemSortedCount('ready_for_screening'));
        $this->assertSame(1, $readiness->systemSortedCount('awaiting_guarantor'));

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString(route('admin.loan-applications.pipeline.system-sorted'), $html);
        $this->assertStringContainsString(route('admin.loan-applications.incomplete'), $html);
        $this->assertStringContainsString(route('admin.loan-applications.pipeline.under-review'), $html);

        $sorted = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.pipeline.system-sorted'))
            ->assertOk();
        $sorted->assertSee('APP-IL-FF24', false)
            ->assertSee('APP-IL-UZ16', false)
            ->assertSee('APP-IL-PENA', false)
            ->assertSee('APP-WL-VQWH', false)
            ->assertSee('APP-IL-LQU6', false)
            ->assertSee('APP-IL-RZNT', false)
            ->assertSee(__('admin.intake.initiate_screening'), false)
            ->assertSee(__('admin.intake.guarantor_replacement_required'), false)
            ->assertDontSee('APP-IL-ZR93', false)
            ->assertDontSee('APP-IL-84SH', false)
            ->assertDontSee('APP-EM-MU8Q', false)
            ->assertDontSee('APP-IL-SCRN', false);

        $this->actingAs($admin, 'admin')
            ->withSession(['locale' => 'sw'])
            ->get(route('admin.loan-applications.pipeline.system-sorted'))
            ->assertOk()
            ->assertSee(__('admin.intake.system_sorted_parked'), false)
            ->assertSee(__('admin.intake.initiate_screening'), false);

        $screening = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.pipeline.under-review'))
            ->assertOk();
        $screening->assertSee('APP-IL-SCRN', false)
            ->assertDontSee('APP-IL-FF24', false)
            ->assertDontSee('APP-IL-LQU6', false)
            ->assertDontSee('APP-IL-RZNT', false)
            ->assertDontSee('APP-IL-ZR93', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.incomplete'))
            ->assertOk()
            ->assertSee(__('admin.application_drafts.title'), false);

        $this->assertSame(2, app(LoanApplicationDraftService::class)->countIncomplete());
        $this->assertSame('draft', LoanApplication::query()->where('application_number', 'APP-IL-ZR93')->value('status'));
        $this->assertSame('withdrawn', LoanApplication::query()->where('application_number', 'APP-EM-MU8Q')->value('status'));
        $this->assertSame('ready_for_screening', LoanApplication::query()->where('application_number', 'APP-IL-LQU6')->value('current_stage'));
        $this->assertSame('screening', LoanApplication::query()->where('application_number', 'APP-IL-SCRN')->value('current_stage'));
    }

    public function test_my_queue_destination_ignores_intake_section_filter(): void
    {
        $admin = $this->staff();
        $this->ready('APP-IL-MINE', $admin->id);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.index', ['mine' => 1]))
            ->assertOk()
            ->assertSee('APP-IL-MINE', false)
            ->assertSee(__('admin.intake.my_queue'), false);
    }

    private function staff(): User
    {
        $branch = Branch::create([
            'code' => 'Q'.random_int(10, 99),
            'name' => 'Queue Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    private function product(): LoanProduct
    {
        return LoanProduct::create([
            'code' => 'Q-'.random_int(100, 999),
            'name' => 'Queue Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100000,
            'max_amount' => 5000000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
        ]);
    }

    private function borrower(): Customer
    {
        return Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-Q-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Queue',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
        ]);
    }

    private function application(
        string $number,
        string $status,
        string $stage,
        ?int $analystId = null,
        array $payload = [],
        bool $submitted = true,
    ): LoanApplication {
        return LoanApplication::create([
            'customer_id' => $this->borrower()->id,
            'loan_product_id' => $this->product()->id,
            'application_number' => $number,
            'requested_amount' => 500000,
            'requested_tenure_months' => 6,
            'status' => $status,
            'current_stage' => $stage,
            'submitted_at' => $submitted ? now() : null,
            'assigned_analyst_id' => $analystId,
            'screening_payload' => $payload,
        ]);
    }

    private function parked(string $number): LoanApplication
    {
        return $this->application($number, 'submitted', 'initial_decision_hold', payload: [
            'intake' => [
                'initial_gate' => [
                    'result' => 'failed',
                    'reason' => 'Borrower failed initial affordability. A guarantor cannot rescue this loan.',
                    'feedback_release_at' => now()->addHours(12)->toIso8601String(),
                ],
            ],
            'capacity_auto_reject' => [
                'status' => CapacityAutoRejectService::STATUS_PENDING,
                'parked_at' => now()->toIso8601String(),
                'auto_reject_at' => now()->addHours(12)->toIso8601String(),
                'settings_key' => 'underwriting.capacity_auto_reject_delay_hours',
            ],
        ]);
    }

    private function ready(string $number, ?int $analystId = null): LoanApplication
    {
        return $this->application($number, 'submitted', 'ready_for_screening', $analystId);
    }

    private function awaitingReplacement(string $number): LoanApplication
    {
        $application = $this->application($number, 'awaiting_guarantor', 'awaiting_guarantor');
        $contact = Guarantor::create([
            'first_name' => 'Lilian',
            'last_name' => 'Declined',
            'phone' => '25576'.random_int(1000000, 9999999),
            'relationship' => 'sibling',
        ]);
        $link = CustomerGuarantor::create([
            'customer_id' => $application->customer_id,
            'guarantor_id' => $contact->id,
            'loan_application_id' => $application->id,
            'status' => 'rejected',
        ]);
        GuarantorInvitation::create([
            'customer_id' => $application->customer_id,
            'customer_guarantor_id' => $link->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $application->loan_product_id,
            'type' => 'external',
            'status' => 'declined',
            'contact' => $contact->phone,
            'invitee_name' => 'Lilian Declined',
            'token' => 'tok-q-'.random_int(10000, 99999),
        ]);

        return $application;
    }

    private function incomplete(string $number): void
    {
        $application = $this->application($number, 'draft', 'draft', submitted: false);
        LoanApplicationDraft::create([
            'customer_id' => $application->customer_id,
            'loan_product_id' => $application->loan_product_id,
            'draft_reference' => $number,
            'phase' => 'application',
            'step' => 2,
            'payload' => ['application_started' => true],
            'saved_at' => now(),
        ]);
    }

    private function withdrawn(string $number): LoanApplication
    {
        return $this->application($number, 'withdrawn', 'withdrawn');
    }

    private function activeScreening(string $number): LoanApplication
    {
        return $this->application($number, 'submitted', 'screening');
    }
}
