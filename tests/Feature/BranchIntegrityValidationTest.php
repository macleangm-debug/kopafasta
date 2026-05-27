<?php

namespace Tests\Feature;

use App\Models\ArrearCase;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchIntegrityValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_role_requires_branch_for_operational_roles(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-int@example.com',
        ]);
        $target = User::factory()->create([
            'role' => 'customer',
            'email' => 'target-int@example.com',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/system/users/'.$target->id.'/assign-role', [
            'role' => 'officer',
        ])->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
    }

    public function test_assign_role_accepts_branch_for_operational_roles(): void
    {
        $branch = Branch::create([
            'code' => 'BRINT01',
            'name' => 'Integrity Branch',
            'region' => 'Region',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-int2@example.com',
        ]);
        $target = User::factory()->create([
            'role' => 'customer',
            'email' => 'target-int2@example.com',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/system/users/'.$target->id.'/assign-role', [
            'role' => 'officer',
            'branch_id' => $branch->id,
            'approval_limit' => 1000,
        ])->assertOk();
    }

    public function test_customer_create_requires_branch(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-cust@example.com',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/customers', [
            'first_name' => 'NoBranch',
            'last_name' => 'Customer',
        ])->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
    }

    public function test_customer_create_succeeds_with_branch(): void
    {
        $branch = Branch::create([
            'code' => 'BRINT02',
            'name' => 'Customer Branch',
            'region' => 'Region',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-cust2@example.com',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/customers', [
            'first_name' => 'WithBranch',
            'last_name' => 'Customer',
            'branch_id' => $branch->id,
        ])->assertStatus(201)->assertJsonFragment([
            'branch_id' => $branch->id,
        ]);
    }

    public function test_loan_application_create_falls_back_to_customer_branch(): void
    {
        $branch = Branch::create([
            'code' => 'BRINT03',
            'name' => 'App Branch',
            'region' => 'Region',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-app@example.com',
        ]);

        $customer = Customer::create([
            'branch_id' => $branch->id,
            'customer_number' => 'CUST-INT-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'App',
            'last_name' => 'Customer',
        ]);

        $product = LoanProduct::create([
            'code' => 'PRDINT01',
            'name' => 'Integrity Product',
            'category' => 'salary_loan',
            'interest_rate' => 10,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'min_amount' => 100,
            'max_amount' => 100000,
            'requires_collateral' => false,
            'requires_guarantor' => false,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/loan-applications', [
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'requested_amount' => 50000,
            'requested_tenure_months' => 6,
        ])->assertStatus(201)->assertJsonFragment([
            'branch_id' => $branch->id,
        ]);
    }

    public function test_loan_application_create_returns_422_when_branch_unresolvable(): void
    {
        // Use raw DB insert to bypass customer validation and create a branchless customer
        $customerId = \DB::table('customers')->insertGetId([
            'customer_number' => 'CUST-INT-002',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'NoBranch',
            'last_name' => 'Customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = LoanProduct::create([
            'code' => 'PRDINT02',
            'name' => 'Integrity Product 2',
            'category' => 'salary_loan',
            'interest_rate' => 10,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'min_amount' => 100,
            'max_amount' => 100000,
            'requires_collateral' => false,
            'requires_guarantor' => false,
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-app2@example.com',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/loan-applications', [
            'customer_id' => $customerId,
            'loan_product_id' => $product->id,
            'requested_amount' => 50000,
            'requested_tenure_months' => 6,
        ])->assertStatus(422)->assertJsonFragment([
            'message' => 'Branch is required for loan application',
        ]);
    }

    public function test_arrear_show_denied_for_officer_from_other_branch(): void
    {
        ['arrearCase' => $arrearCase] = $this->createArrearGraph();

        $otherBranch = Branch::create([
            'code' => 'BRINT04',
            'name' => 'Other Branch',
            'region' => 'Region',
            'is_active' => true,
        ]);

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'officer-arr1@example.com',
            'branch_id' => $otherBranch->id,
        ]);

        Sanctum::actingAs($officer);

        $this->getJson('/api/arrears/'.$arrearCase->id)->assertStatus(403);
    }

    public function test_arrear_show_allowed_for_officer_in_same_branch(): void
    {
        ['arrearCase' => $arrearCase, 'branch' => $branch] = $this->createArrearGraph();

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'officer-arr2@example.com',
            'branch_id' => $branch->id,
        ]);

        Sanctum::actingAs($officer);

        $this->getJson('/api/arrears/'.$arrearCase->id)->assertOk();
    }

    public function test_arrear_add_action_denied_when_officer_has_no_branch(): void
    {
        ['arrearCase' => $arrearCase] = $this->createArrearGraph();

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'officer-arr3@example.com',
            'branch_id' => null,
        ]);

        Sanctum::actingAs($officer);

        $this->postJson('/api/arrears/'.$arrearCase->id.'/actions', [
            'action_type' => 'call',
            'notes' => 'Attempted contact',
        ])->assertStatus(403);
    }

    public function test_loan_application_view_denied_from_other_branch(): void
    {
        ['loanApplication' => $loanApplication] = $this->createArrearGraph();

        $otherBranch = Branch::create([
            'code' => 'BRINT05',
            'name' => 'Other LA Branch',
            'region' => 'Region',
            'is_active' => true,
        ]);

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'officer-la1@example.com',
            'branch_id' => $otherBranch->id,
        ]);

        Sanctum::actingAs($officer);

        $this->getJson('/api/loan-applications/'.$loanApplication->id)->assertStatus(403);
    }

    public function test_loan_application_transition_denied_from_other_branch(): void
    {
        ['loanApplication' => $loanApplication] = $this->createArrearGraph();

        $otherBranch = Branch::create([
            'code' => 'BRINT06',
            'name' => 'Other LA Branch 2',
            'region' => 'Region',
            'is_active' => true,
        ]);

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager-la1@example.com',
            'branch_id' => $otherBranch->id,
            'approval_limit' => 100000000,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/loan-applications/'.$loanApplication->id.'/transition', [
            'to_stage' => 'pre_approval',
        ])->assertStatus(403);
    }

    public function test_loan_application_show_resolves_route_binding(): void
    {
        ['loanApplication' => $loanApplication] = $this->createArrearGraph();

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-bind1@example.com',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/loan-applications/'.$loanApplication->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $loanApplication->id]);
    }

    public function test_restructure_show_resolves_route_binding(): void
    {
        ['loan' => $loan] = $this->createArrearGraph();

        $restructure = \App\Models\RestructureRequest::create([
            'loan_id' => $loan->id,
            'reason' => 'Binding test',
            'new_tenure_months' => 18,
            'new_interest_rate' => 11.0,
            'status' => 'pending',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-bind2@example.com',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/restructures/'.$restructure->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $restructure->id]);
    }

    public function test_vendor_task_show_resolves_route_binding(): void
    {
        $vendor = \App\Models\Vendor::create([
            'vendor_number' => 'V-BIND-001',
            'name' => 'Bind Vendor',
            'category' => 'valuation',
            'status' => 'active',
        ]);

        $vendorTask = \App\Models\VendorTask::create([
            'vendor_id' => $vendor->id,
            'task_type' => 'valuation',
            'status' => 'assigned',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-bind3@example.com',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/vendor-tasks/'.$vendorTask->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $vendorTask->id]);
    }

    public function test_arrear_update_resolves_route_binding(): void
    {
        ['arrearCase' => $arrearCase, 'branch' => $branch] = $this->createArrearGraph();

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'officer-bind@example.com',
            'branch_id' => $branch->id,
        ]);

        Sanctum::actingAs($officer);

        $this->putJson('/api/arrears/'.$arrearCase->id, [
            'status' => 'in_progress',
        ])->assertOk()
            ->assertJsonFragment(['id' => $arrearCase->id, 'status' => 'in_progress']);
    }

    /**
     * @return array<string, mixed>
     */
    private function createArrearGraph(): array
    {
        $branch = Branch::create([
            'code' => 'ARRINT01',
            'name' => 'Arrear Branch',
            'region' => 'Region',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'branch_id' => $branch->id,
            'customer_number' => 'CUST-ARR-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Arrear',
            'last_name' => 'Customer',
        ]);

        $product = LoanProduct::create([
            'code' => 'PRDARR01',
            'name' => 'Arrear Product',
            'category' => 'salary_loan',
            'interest_rate' => 12,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
            'min_amount' => 100,
            'max_amount' => 10000000,
            'requires_collateral' => false,
            'requires_guarantor' => false,
            'is_active' => true,
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-ARR-001',
            'principal_amount' => 500000,
            'interest_rate' => 12,
            'tenure_months' => 12,
            'approved_amount' => 500000,
            'outstanding_balance' => 500000,
            'status' => 'active',
        ]);

        $arrearCase = ArrearCase::create([
            'loan_id' => $loan->id,
            'days_past_due' => 30,
            'amount_in_arrears' => 50000,
            'penalty_amount' => 2500,
            'status' => 'open',
        ]);

        $loanApplication = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-ARR-001',
            'requested_amount' => 500000,
            'requested_tenure_months' => 12,
            'status' => 'submitted',
            'current_stage' => 'submitted',
            'submitted_at' => now(),
        ]);

        return [
            'branch' => $branch,
            'customer' => $customer,
            'loan' => $loan,
            'arrearCase' => $arrearCase,
            'loanApplication' => $loanApplication,
        ];
    }
}
