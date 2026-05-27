<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Repayment;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResourcePoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_show_denied_for_officer_from_other_branch(): void
    {
        [$branch, $customer] = $this->makeBranchCustomer('CPB1', 'C-CPB1');

        $otherBranch = Branch::create([
            'code' => 'CPB1X',
            'name' => 'Other',
            'region' => 'R',
            'is_active' => true,
        ]);
        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'cust-other@example.com',
            'branch_id' => $otherBranch->id,
        ]);

        Sanctum::actingAs($officer);

        $this->getJson('/api/customers/'.$customer->id)->assertStatus(403);
    }

    public function test_customer_show_allowed_for_officer_in_same_branch(): void
    {
        [$branch, $customer] = $this->makeBranchCustomer('CPB2', 'C-CPB2');

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'cust-same@example.com',
            'branch_id' => $branch->id,
        ]);

        Sanctum::actingAs($officer);

        $this->getJson('/api/customers/'.$customer->id)->assertOk();
    }

    public function test_customer_delete_denied_for_officer(): void
    {
        [$branch, $customer] = $this->makeBranchCustomer('CPB3', 'C-CPB3');

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'cust-del@example.com',
            'branch_id' => $branch->id,
        ]);

        Sanctum::actingAs($officer);

        $this->deleteJson('/api/customers/'.$customer->id)->assertStatus(403);
    }

    public function test_loan_product_create_denied_for_officer_allowed_for_manager(): void
    {
        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'prod-off@example.com',
        ]);
        Sanctum::actingAs($officer);

        $payload = [
            'code' => 'PRD-POL1',
            'name' => 'Policy Product',
            'category' => 'salary_loan',
            'interest_rate' => 10,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'min_amount' => 100,
            'max_amount' => 100000,
        ];

        $this->postJson('/api/loan-products', $payload)->assertStatus(403);

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'prod-mgr@example.com',
            'branch_id' => Branch::create([
                'code' => 'PRD-BR',
                'name' => 'B',
                'region' => 'R',
                'is_active' => true,
            ])->id,
        ]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/loan-products', $payload)->assertStatus(201);
    }

    public function test_loan_product_delete_requires_admin(): void
    {
        $product = LoanProduct::create([
            'code' => 'PRD-DEL',
            'name' => 'Del',
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

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'prod-del-mgr@example.com',
            'branch_id' => Branch::create([
                'code' => 'PRD-DELBR',
                'name' => 'B',
                'region' => 'R',
                'is_active' => true,
            ])->id,
        ]);
        Sanctum::actingAs($manager);
        $this->deleteJson('/api/loan-products/'.$product->id)->assertStatus(403);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'prod-del-admin@example.com',
        ]);
        Sanctum::actingAs($admin);
        $this->deleteJson('/api/loan-products/'.$product->id)->assertStatus(204);
    }

    public function test_vendor_delete_requires_admin(): void
    {
        $vendor = Vendor::create([
            'vendor_number' => 'V-POL-1',
            'name' => 'V',
            'category' => 'valuation',
            'status' => 'active',
        ]);

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'vendor-mgr@example.com',
            'branch_id' => Branch::create([
                'code' => 'V-BR',
                'name' => 'B',
                'region' => 'R',
                'is_active' => true,
            ])->id,
        ]);
        Sanctum::actingAs($manager);
        $this->deleteJson('/api/vendors/'.$vendor->id)->assertStatus(403);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'vendor-admin@example.com',
        ]);
        Sanctum::actingAs($admin);
        $this->deleteJson('/api/vendors/'.$vendor->id)->assertStatus(204);
    }

    public function test_vendor_task_delete_requires_manager_or_admin(): void
    {
        $vendor = Vendor::create([
            'vendor_number' => 'V-POL-2',
            'name' => 'V',
            'category' => 'valuation',
            'status' => 'active',
        ]);
        $task = VendorTask::create([
            'vendor_id' => $vendor->id,
            'task_type' => 'valuation',
            'status' => 'assigned',
        ]);

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'vt-off@example.com',
        ]);
        Sanctum::actingAs($officer);
        $this->deleteJson('/api/vendor-tasks/'.$task->id)->assertStatus(403);

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'vt-mgr@example.com',
            'branch_id' => Branch::create([
                'code' => 'VT-BR',
                'name' => 'B',
                'region' => 'R',
                'is_active' => true,
            ])->id,
        ]);
        Sanctum::actingAs($manager);
        $this->deleteJson('/api/vendor-tasks/'.$task->id)->assertStatus(204);
    }

    public function test_repayment_show_denied_across_branches(): void
    {
        [$branch, $customer] = $this->makeBranchCustomer('RPB1', 'C-RPB1');

        $product = LoanProduct::create([
            'code' => 'PRD-RPM',
            'name' => 'Repayment Product',
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

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-RPM-1',
            'principal_amount' => 500000,
            'interest_rate' => 10,
            'tenure_months' => 12,
            'approved_amount' => 500000,
            'outstanding_balance' => 500000,
            'status' => 'active',
        ]);

        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'reference' => 'RCP-POL-1',
            'channel' => 'cash',
            'amount' => 50000,
            'status' => 'received',
            'principal_component' => 50000,
            'paid_at' => now(),
        ]);

        $otherBranch = Branch::create([
            'code' => 'RPB1X',
            'name' => 'Other',
            'region' => 'R',
            'is_active' => true,
        ]);
        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'rpm-other@example.com',
            'branch_id' => $otherBranch->id,
        ]);
        Sanctum::actingAs($officer);

        $this->getJson('/api/repayments/'.$repayment->id)->assertStatus(403);

        $sameBranchOfficer = User::factory()->create([
            'role' => 'officer',
            'email' => 'rpm-same@example.com',
            'branch_id' => $branch->id,
        ]);
        Sanctum::actingAs($sameBranchOfficer);

        $this->getJson('/api/repayments/'.$repayment->id)->assertOk();
    }

    /**
     * @return array{0: Branch, 1: Customer}
     */
    private function makeBranchCustomer(string $code, string $custNumber): array
    {
        $branch = Branch::create([
            'code' => $code,
            'name' => 'Branch '.$code,
            'region' => 'R',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'branch_id' => $branch->id,
            'customer_number' => $custNumber,
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Pol',
            'last_name' => 'Test',
        ]);

        return [$branch, $customer];
    }
}
