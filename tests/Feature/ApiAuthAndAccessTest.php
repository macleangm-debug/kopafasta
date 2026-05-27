<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\RestructureRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthAndAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'officer@example.com',
            'password' => bcrypt('password'),
            'role' => 'officer',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'officer@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_system_routes(): void
    {
        $response = $this->getJson('/api/system/users');

        $response->assertStatus(401);
    }

    public function test_customer_cannot_access_system_routes(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'email' => 'customer@example.com',
        ]);

        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/system/users');

        $response->assertStatus(403);
    }

    public function test_manager_can_access_system_routes(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager@example.com',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/system/users');

        $response->assertOk();
    }

    public function test_customer_cannot_access_reports_but_officer_can(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'email' => 'customer2@example.com',
        ]);

        Sanctum::actingAs($customer);
        $this->getJson('/api/reports/portfolio')->assertStatus(403);

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'officer2@example.com',
        ]);

        Sanctum::actingAs($officer);
        $this->getJson('/api/reports/portfolio')->assertOk();
    }

    public function test_officer_cannot_disburse_loan_but_manager_can(): void
    {
        ['loan' => $loan, 'branch' => $branch] = $this->createLoanGraph();

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'officer3@example.com',
        ]);
        Sanctum::actingAs($officer);
        $this->postJson('/api/loans/'.$loan->id.'/disburse')->assertStatus(403);

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager2@example.com',
            'branch_id' => $branch->id,
        ]);
        Sanctum::actingAs($manager);
        $this->postJson('/api/loans/'.$loan->id.'/disburse')->assertOk();
    }

    public function test_officer_cannot_delete_disbursement_but_admin_can(): void
    {
        ['disbursement' => $disbursement] = $this->createLoanGraph();

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'officer4@example.com',
        ]);
        Sanctum::actingAs($officer);
        $this->deleteJson('/api/disbursements/'.$disbursement->id)->assertStatus(403);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin2@example.com',
        ]);
        Sanctum::actingAs($admin);
        $this->deleteJson('/api/disbursements/'.$disbursement->id)->assertStatus(204);
    }

    public function test_officer_cannot_transition_loan_application_but_manager_can(): void
    {
        ['loanApplication' => $loanApplication, 'branch' => $branch] = $this->createLoanGraph();

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'officer5@example.com',
        ]);
        Sanctum::actingAs($officer);
        $this->postJson('/api/loan-applications/'.$loanApplication->id.'/transition', [
            'to_stage' => 'screening',
        ])->assertStatus(403);

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager3@example.com',
            'branch_id' => $branch->id,
            'approval_limit' => 1000000,
        ]);
        Sanctum::actingAs($manager);
        $this->postJson('/api/loan-applications/'.$loanApplication->id.'/transition', [
            'to_stage' => 'screening',
        ])->assertOk();
    }

    public function test_officer_cannot_approve_restructure_but_manager_can(): void
    {
        ['restructureRequest' => $restructureRequest, 'branch' => $branch] = $this->createLoanGraph();

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'officer6@example.com',
        ]);
        Sanctum::actingAs($officer);
        $this->postJson('/api/restructures/'.$restructureRequest->id.'/approve')->assertStatus(403);

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager4@example.com',
            'branch_id' => $branch->id,
            'approval_limit' => 1000000,
        ]);
        Sanctum::actingAs($manager);
        $this->postJson('/api/restructures/'.$restructureRequest->id.'/approve')->assertOk();
    }

    public function test_manager_cannot_transition_to_approval_stage_when_over_limit(): void
    {
        ['loanApplication' => $loanApplication, 'branch' => $branch] = $this->createLoanGraph();

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager5@example.com',
            'branch_id' => $branch->id,
            'approval_limit' => 100000,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/loan-applications/'.$loanApplication->id.'/transition', [
            'to_stage' => 'approval',
        ])->assertStatus(422)->assertJsonFragment([
            'message' => 'Approval limit exceeded',
        ]);
    }

    public function test_manager_cannot_approve_restructure_when_over_limit(): void
    {
        ['restructureRequest' => $restructureRequest, 'branch' => $branch] = $this->createLoanGraph();

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager6@example.com',
            'branch_id' => $branch->id,
            'approval_limit' => 100000,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/restructures/'.$restructureRequest->id.'/approve')
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Approval limit exceeded',
            ]);
    }

    public function test_manager_cannot_release_disbursement_when_over_limit(): void
    {
        ['disbursement' => $disbursement, 'branch' => $branch] = $this->createLoanGraph();

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager7@example.com',
            'branch_id' => $branch->id,
            'approval_limit' => 100000,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/disbursements/'.$disbursement->id.'/release')
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Approval limit exceeded',
            ]);
    }

    public function test_manager_can_release_disbursement_within_limit(): void
    {
        ['disbursement' => $disbursement, 'branch' => $branch] = $this->createLoanGraph();

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager8@example.com',
            'branch_id' => $branch->id,
            'approval_limit' => 1000000,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/disbursements/'.$disbursement->id.'/release')
            ->assertOk()
            ->assertJsonFragment([
                'status' => 'released',
            ]);
    }

    public function test_manager_cannot_release_disbursement_from_other_branch(): void
    {
        ['disbursement' => $disbursement, 'branch' => $branch] = $this->createLoanGraph();

        $otherBranch = Branch::create([
            'code' => 'TST002',
            'name' => 'Other Branch',
            'region' => 'Other Region',
            'is_active' => true,
        ]);

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager9@example.com',
            'branch_id' => $otherBranch->id,
            'approval_limit' => 1000000,
        ]);

        $this->assertNotSame($branch->id, $manager->branch_id);

        Sanctum::actingAs($manager);

        $this->postJson('/api/disbursements/'.$disbursement->id.'/release')->assertStatus(403);
    }

    public function test_manager_can_transition_loan_application_in_same_branch(): void
    {
        ['loanApplication' => $loanApplication, 'branch' => $branch] = $this->createLoanGraph();

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager10@example.com',
            'branch_id' => $branch->id,
            'approval_limit' => 1000000,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/loan-applications/'.$loanApplication->id.'/transition', [
            'to_stage' => 'approval',
        ])->assertOk();
    }

    public function test_manager_without_branch_cannot_release_disbursement_even_with_limit(): void
    {
        ['disbursement' => $disbursement] = $this->createLoanGraph();

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager11@example.com',
            'branch_id' => null,
            'approval_limit' => 1000000,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/disbursements/'.$disbursement->id.'/release')->assertStatus(403);
    }

    public function test_manager_cannot_approve_restructure_from_other_branch(): void
    {
        ['restructureRequest' => $restructureRequest] = $this->createLoanGraph();

        $otherBranch = Branch::create([
            'code' => 'TST003',
            'name' => 'Different Branch',
            'region' => 'Different Region',
            'is_active' => true,
        ]);

        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager12@example.com',
            'branch_id' => $otherBranch->id,
            'approval_limit' => 1000000,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/restructures/'.$restructureRequest->id.'/approve')->assertStatus(403);
    }

    /**
     * Build a minimal loan graph needed for policy tests.
     *
     * @return array<string, mixed>
     */
    private function createLoanGraph(): array
    {
        $branch = Branch::create([
            'code' => 'TST001',
            'name' => 'Test Branch',
            'region' => 'Test Region',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'branch_id' => $branch->id,
            'customer_number' => 'CUST-TST-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Policy',
            'last_name' => 'Customer',
        ]);

        $product = LoanProduct::create([
            'code' => 'PRD001',
            'name' => 'Policy Test Product',
            'category' => 'salary_loan',
            'interest_rate' => 12,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
            'min_amount' => 100000,
            'max_amount' => 10000000,
            'requires_collateral' => false,
            'requires_guarantor' => false,
            'is_active' => true,
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-TST-001',
            'principal_amount' => 500000,
            'interest_rate' => 12,
            'tenure_months' => 12,
            'approved_amount' => 500000,
            'outstanding_balance' => 500000,
            'status' => 'approved',
        ]);

        $disbursement = Disbursement::create([
            'loan_id' => $loan->id,
            'reference' => 'DSB-TST-001',
            'channel' => 'bank_transfer',
            'amount' => 500000,
            'status' => 'pending',
        ]);

        $loanApplication = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-TST-001',
            'requested_amount' => 500000,
            'requested_tenure_months' => 12,
            'status' => 'submitted',
            'current_stage' => 'submitted',
            'submitted_at' => now(),
        ]);

        $restructureRequest = RestructureRequest::create([
            'loan_id' => $loan->id,
            'reason' => 'Temporary cash flow constraints',
            'new_tenure_months' => 18,
            'new_interest_rate' => 11.5,
            'status' => 'pending',
        ]);

        return [
            'branch' => $branch,
            'customer' => $customer,
            'product' => $product,
            'loan' => $loan,
            'disbursement' => $disbursement,
            'loanApplication' => $loanApplication,
            'restructureRequest' => $restructureRequest,
        ];
    }
}
