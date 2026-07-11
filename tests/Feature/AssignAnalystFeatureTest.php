<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignAnalystFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_and_clear_analyst(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $analyst = User::factory()->create(['role' => 'credit_analyst', 'name' => 'Assigned Analyst']);

        $customer = Customer::create([
            'customer_number' => 'CU-ASSIGN-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Assign',
            'last_name'       => 'Borrower',
            'phone'           => '255712349901',
        ]);

        $product = LoanProduct::create([
            'code'              => 'IL-ASSIGN',
            'name'              => 'Assign Test Loan',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 2_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-ASSIGN-001',
            'status'                  => 'under_review',
            'current_stage'           => 'screening',
            'requested_amount'        => 250_000,
            'requested_tenure_months' => 6,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.assign-analyst', $application), [
                'assigned_analyst_id' => $analyst->id,
            ])
            ->assertRedirect(route('admin.loan-applications.show', $application));

        $application->refresh();
        $this->assertSame($analyst->id, $application->assigned_analyst_id);
        $this->assertNotNull($application->assigned_at);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.assign-analyst', $application), [
                'assigned_analyst_id' => null,
            ])
            ->assertRedirect(route('admin.loan-applications.show', $application));

        $application->refresh();
        $this->assertNull($application->assigned_analyst_id);
        $this->assertNull($application->assigned_at);
    }
}
