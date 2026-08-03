<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditWorkspaceUiFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'admin'): User
    {
        $branch = Branch::create([
            'code'      => 'CW'.random_int(10, 99),
            'name'      => 'CW Branch',
            'region'    => 'Dar',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role'      => $role,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    private function application(User $actor, string $stage): LoanApplication
    {
        $product = LoanProduct::create([
            'code'              => 'CW-'.random_int(100, 999),
            'name'              => 'CW Product',
            'is_active'         => true,
            'interest_rate'     => 0.18,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $customer = Customer::create([
            'user_id'         => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-CW-'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Workspace',
            'last_name'       => 'Borrower',
            'phone'           => '25571'.random_int(1000000, 9999999),
            'branch_id'       => $actor->branch_id,
        ]);

        return LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'branch_id'               => $actor->branch_id,
            'application_number'      => 'APP-CW-'.random_int(1000, 9999),
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 6,
            'status'                  => 'under_review',
            'current_stage'           => $stage,
            'submitted_at'            => now(),
        ]);
    }

    public function test_screening_and_committee_share_premium_workspace_with_tabs(): void
    {
        $admin = $this->staff();

        foreach (['screening', 'credit_appraisal', 'pre_approval'] as $stage) {
            $app = $this->application($admin, $stage);
            $html = $this->actingAs($admin, 'admin')
                ->get(route('admin.loan-applications.show', $app))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('Borrower file', $html);
            $this->assertStringContainsString('Facility summary', $html);
            $this->assertStringContainsString('Risk score', $html);
            $this->assertStringContainsString('CRB suggestion', $html);
            $this->assertStringContainsString('Guarantor', $html);
            $this->assertStringContainsString('tab=face', $html);
            $this->assertStringContainsString('tab=guarantor', $html);
        }

        $app = $this->application($admin, 'screening');
        $screening = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Screening workspace', $screening);
        $this->assertStringContainsString('Record your screening recommendation', $screening);
        $this->assertStringContainsString('Push recommendation to committee', $screening);
        $this->assertStringContainsString('data-open-dialog="recommend-'.$app->id.'"', $screening);
        $this->assertStringNotContainsString('Record the committee decision', $screening);

        $residence = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'tab' => 'residence']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Residence information', $residence);
        $this->assertStringContainsString('aria-selected="true"', $residence);

        $face = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'tab' => 'face']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Side-by-side comparison', $face);
        $this->assertStringContainsString('Primary check', $face);
        $this->assertStringContainsString('Front face not uploaded', $face);

        $documents = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'tab' => 'documents']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Requested documents', $documents);
        $this->assertStringContainsString('Product document checklist', $documents);
        $this->assertStringContainsString('Request another document', $documents);

        $guarantor = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'tab' => 'guarantor']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Guarantor review', $guarantor);

        $committee = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $this->application($admin, 'pre_approval')))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Committee workspace', $committee);
        $this->assertStringContainsString('Record the committee decision', $committee);
        $this->assertStringContainsString('CRB suggestion vs screening recommendation', $committee);
    }

    public function test_management_stage_uses_premium_ops_workspace(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'approval');
        $app->forceFill([
            'offered_amount' => 400_000,
            'offer_status'   => 'accepted',
            'approved_at'    => now(),
        ])->save();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Credit management workspace', $html);
        $this->assertStringContainsString('Approved facility', $html);
        $this->assertStringContainsString('Release readiness', $html);
        $this->assertStringContainsString('Borrower file', $html);
        $this->assertStringContainsString('Guarantor', $html);
        $this->assertStringNotContainsString('CRB suggestion', $html);
    }
}
