<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\CapacityAutoRejectService;
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
}
