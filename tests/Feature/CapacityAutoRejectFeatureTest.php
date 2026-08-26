<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\CapacityAutoRejectService;
use App\Services\LoanApplicationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CapacityAutoRejectFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('underwriting.enable_automatic_rejection', true);
        Setting::set('underwriting.enable_capacity_auto_reject', true);
        Setting::set('underwriting.capacity_auto_reject_delay_hours', 12);
    }

    public function test_capacity_fail_is_parked_then_fired_with_numbered_borrower_message(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-CAP-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Low',
            'last_name' => 'Income',
            'phone' => '255712340001',
            'monthly_income' => 100_000,
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-CAP',
            'name' => 'Capacity Test',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-CAP-001',
            'requested_amount' => 2_000_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);

        $service = app(CapacityAutoRejectService::class);
        $state = $service->evaluateAndPark($application->fresh(['customer', 'product']));

        $this->assertNotNull($state);
        $this->assertSame(CapacityAutoRejectService::STATUS_PENDING, $state['status']);
        $this->assertTrue($service->isPending($application->fresh()));

        Carbon::setTestNow(now()->addHours(13));

        $fired = $service->fireDue();
        $this->assertCount(1, $fired);

        $application->refresh();
        $this->assertSame('rejected', $application->status);
        $this->assertSame('rejected', $application->current_stage);
        $this->assertSame(CapacityAutoRejectService::REASON_CODE, $application->rejection_reason_code);
        $this->assertStringContainsString(format_money(2_000_000), (string) $application->rejection_reason);
        $this->assertStringContainsString('per month', (string) $application->rejection_reason);
        $this->assertDatabaseHas('loan_agreements', [
            'loan_application_id' => $application->id,
            'document_type' => 'rejection_letter',
        ]);

        $letter = LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'rejection_letter')
            ->first();
        $this->assertNotNull($letter);
        $this->assertContains('repayment_exceeds_limit', $letter->snapshot['rejection_codes'] ?? []);
        $this->assertContains(
            __('rejection.reasons.repayment_exceeds_limit', [], 'sw'),
            $letter->snapshot['rejection_reasons'] ?? [],
        );
        $this->assertStringContainsString(format_money(2_000_000), (string) ($letter->snapshot['rejection_detail'] ?? ''));

        Carbon::setTestNow();
    }

    public function test_admin_can_cancel_pending_auto_reject(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $customer = Customer::create([
            'customer_number' => 'CU-CAP-002',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Keep',
            'last_name' => 'Screening',
            'phone' => '255712340002',
            'monthly_income' => 50_000,
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-CAP2',
            'name' => 'Capacity Cancel',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-CAP-002',
            'requested_amount' => 3_000_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);

        $service = app(CapacityAutoRejectService::class);
        $service->evaluateAndPark($application->fresh(['customer', 'product']));
        $this->assertTrue($service->isPending($application->fresh()));

        $response = $this->actingAs($admin, 'admin')
            ->from(route('admin.loan-applications.show', $application))
            ->post(route('admin.loan-applications.capacity-auto-reject.cancel', $application));

        $response->assertRedirect(route('admin.loan-applications.show', $application));
        $response->assertSessionHas('status');

        $application->refresh();
        $this->assertSame(
            CapacityAutoRejectService::STATUS_CANCELLED,
            data_get($application->screening_payload, 'capacity_auto_reject.status'),
            json_encode($application->screening_payload)
        );
        $this->assertFalse($service->isPending($application));
        $this->assertSame('submitted', $application->status);
    }

    public function test_affordability_pass_is_not_parked(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-CAP-003',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'High',
            'last_name' => 'Income',
            'phone' => '255712340003',
            'monthly_income' => 5_000_000,
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-CAP3',
            'name' => 'Capacity Pass',
            'is_active' => true,
            'interest_rate' => 0.02,
            'min_amount' => 50_000,
            'max_amount' => 500_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-CAP-003',
            'requested_amount' => 100_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);

        $state = app(CapacityAutoRejectService::class)->evaluateAndPark($application->fresh(['customer', 'product']));
        $this->assertNull($state);
    }

    public function test_profile_income_auto_reject_still_fires_after_12_hours_when_documents_are_outstanding(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $customer = Customer::create([
            'customer_number' => 'CU-CAP-004',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Profile',
            'last_name' => 'Income',
            'phone' => '255712340004',
            'monthly_income' => 80_000,
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-CAP4',
            'name' => 'Profile Capacity',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-CAP-004',
            'requested_amount' => 2_500_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);

        LoanApplicationDocumentRequest::create([
            'loan_application_id' => $application->id,
            'requested_by' => $admin->id,
            'label' => 'National ID (front)',
            'type' => 'document',
            'status' => 'pending',
        ]);

        $service = app(CapacityAutoRejectService::class);
        $state = $service->evaluateAndPark($application->fresh(['customer', 'product']));

        $this->assertNotNull($state);
        $this->assertSame(12, (int) $state['delay_hours']);
        $this->assertSame('declared', data_get(
            $application->fresh()->credit_appraisal_payload,
            'affordability.income_basis'
        ));
        $this->assertNotEmpty(
            app(LoanApplicationWorkflowService::class)->screeningDocumentBlockers($application->fresh())
        );

        $this->actingAs($admin, 'admin')
            ->from(route('admin.loan-applications.show', ['loan_application' => $application, 'workspace' => 'decision']))
            ->post(route('admin.loan-applications.workflow', $application), [
                'action' => 'submit_recommendation',
                'recommendation_type' => 'approve',
                'remarks' => 'Trying to approve with docs still out.',
            ])
            ->assertSessionHasErrors('action');

        $application->refresh();
        $this->assertNull($application->recommendation_type);
        $this->assertSame('screening', $application->current_stage);
        $this->assertTrue($service->isPending($application));

        Carbon::setTestNow(now()->addHours(11));
        $this->assertCount(0, $service->fireDue());
        $this->assertSame('submitted', $application->fresh()->status);

        Carbon::setTestNow(now()->addHours(2));
        $this->assertCount(1, $service->fireDue());

        $application->refresh();
        $this->assertSame('rejected', $application->status);
        $this->assertSame('rejected', $application->current_stage);
        $this->assertSame(CapacityAutoRejectService::REASON_CODE, $application->rejection_reason_code);

        Carbon::setTestNow();
    }

    public function test_credit_manager_cannot_send_or_cancel_capacity_auto_reject(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $application = $this->parkedLowIncomeApplication('CU-CAP-005', 'APP-CAP-005');

        $service = app(CapacityAutoRejectService::class);
        $this->assertFalse($service->canAct($manager));
        $this->assertTrue($service->isPending($application->fresh()));

        $this->actingAs($manager, 'admin')
            ->post(route('admin.loan-applications.capacity-auto-reject.cancel', $application))
            ->assertForbidden();

        $this->actingAs($manager, 'admin')
            ->post(route('admin.loan-applications.capacity-auto-reject.fire', $application))
            ->assertForbidden();

        $html = $this->actingAs($manager, 'admin')
            ->get(route('admin.loan-applications.show', $application))
            ->assertForbidden()
            ->getContent();
        $this->assertTrue($service->isPending($application->fresh()));
    }

    public function test_credit_committee_can_keep_parked_application_in_screening(): void
    {
        $committee = User::factory()->create(['role' => 'credit_committee', 'is_active' => true]);
        $application = $this->parkedLowIncomeApplication('CU-CAP-006', 'APP-CAP-006');

        $service = app(CapacityAutoRejectService::class);
        $this->assertTrue($service->canAct($committee));

        $html = $this->actingAs($committee, 'admin')
            ->get(route('admin.loan-applications.show', $application))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Send rejection now', $html);
        $this->assertStringContainsString('Keep in screening', $html);

        $this->actingAs($committee, 'admin')
            ->from(route('admin.loan-applications.show', $application))
            ->post(route('admin.loan-applications.capacity-auto-reject.cancel', $application))
            ->assertRedirect();

        $this->assertSame(
            CapacityAutoRejectService::STATUS_CANCELLED,
            data_get($application->fresh()->screening_payload, 'capacity_auto_reject.status')
        );
        $this->assertFalse($service->isPending($application->fresh()));
    }

    private function parkedLowIncomeApplication(string $customerNumber, string $applicationNumber): LoanApplication
    {
        $customer = Customer::create([
            'customer_number' => $customerNumber,
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Parked',
            'last_name' => 'Borrower',
            'phone' => '25571234'.random_int(1000, 9999),
            'monthly_income' => 50_000,
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-'.random_int(1000, 9999),
            'name' => 'Parked Capacity',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => $applicationNumber,
            'requested_amount' => 3_000_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);

        app(CapacityAutoRejectService::class)->evaluateAndPark($application->fresh(['customer', 'product']));

        return $application->fresh();
    }
}
