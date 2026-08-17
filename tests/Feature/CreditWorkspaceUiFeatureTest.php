<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
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

            $this->assertStringContainsString('Facility summary', $html);
            $this->assertStringContainsString('Risk score', $html);
            $this->assertStringContainsString('Borrower CRB', $html);
            $this->assertStringContainsString('Open guarantor file', $html);
            $this->assertStringContainsString('Review checklist', $html);
            $this->assertStringContainsString('workspace=profiles', $html);
            $this->assertStringContainsString('workspace=decision', $html);

            $profiles = $this->actingAs($admin, 'admin')
                ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'profiles']))
                ->assertOk()
                ->getContent();
            $this->assertStringContainsString('Profile sections', $profiles);
            $this->assertStringContainsString('tab=face', $profiles);
            $this->assertStringContainsString('person=guarantor', $profiles);
            $this->assertStringNotContainsString('Partners available', $profiles);
            $this->assertStringNotContainsString('Partners unavailable', $profiles);
            $this->assertStringNotContainsString('>Group</a>', $profiles);
            $this->assertStringContainsString('tab=personal', $profiles);
            $this->assertStringNotContainsString("tab=affordability", $profiles);
            $this->assertStringNotContainsString("tab=crb", $profiles);
        }

        $app = $this->application($admin, 'screening');
        $screeningHome = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Screening workspace', $screeningHome);
        $this->assertStringContainsString('Other institutions', $screeningHome);
        $this->assertStringContainsString('Affordability', $screeningHome);
        $this->assertStringNotContainsString('Profile complete', $screeningHome);
        $this->assertStringNotContainsString('Record the committee decision', $screeningHome);

        $screening = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'decision']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Record the screening recommendation', $screening);
        $this->assertStringContainsString('Max this income can support', $screening);
        $this->assertStringContainsString('Your decision', $screening);
        $this->assertStringContainsString('Record decision', $screening);
        $this->assertStringContainsString('Record your decision', $screening);
        $this->assertStringContainsString('Why are you approving?', $screening);
        $this->assertStringContainsString('Push to Committee', $screening);
        $this->assertStringContainsString('Review checklist → Docs', $screening);
        $this->assertStringNotContainsString('Need files? Request them on the Documents tab.', $screening);
        $this->assertStringNotContainsString('Who you are reviewing', $screening);
        $this->assertStringNotContainsString('Preferred reject reason', $screening);
        $this->assertStringNotContainsString('Return for documents', $screening);
        $this->assertStringContainsString('Advice for borrower', $screening);
        $this->assertStringContainsString('Rejection reasons', $screening);
        $this->assertStringContainsString('Open field advice', $screening);
        $this->assertStringNotContainsString('Complete screening', $screening);

        $residence = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'profiles', 'tab' => 'residence']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Residence information', $residence);
        $this->assertStringContainsString('aria-selected="true"', $residence);

        $face = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'profiles', 'tab' => 'face']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Side-by-side comparison', $face);
        $this->assertStringContainsString('Primary check', $face);
        $this->assertStringContainsString('Front face not uploaded', $face);

        $documents = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'checklist']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Personal in place', $documents);
        $this->assertStringContainsString('Capacity and evidence', $documents);
        $this->assertStringContainsString('Security and close', $documents);
        $this->assertStringContainsString('Application evidence', $documents);
        $this->assertStringContainsString('Document library', $documents);
        $this->assertStringContainsString('Request documents', $documents);
        $this->assertStringContainsString('Checklist', $documents);
        $this->assertStringContainsString('Requested', $documents);
        $this->assertStringContainsString('Library', $documents);
        $this->assertStringContainsString('Affordability', $documents);
        $this->assertStringContainsString('Credit file wrap-up', $documents);
        $this->assertStringContainsString('Hold — finish checklist', $documents);
        $this->assertStringContainsString('Gate 2', $documents);
        $this->assertStringContainsString('Match statements to profile revenue', $documents);
        $this->assertStringContainsString('Statement totals', $documents);
        $this->assertStringContainsString('Period is always 6 months', $documents);
        $this->assertStringNotContainsString('Max repayment (1/3)', $documents);
        $this->assertStringNotContainsString('Partners for this file', $documents);
        $this->assertStringNotContainsString('Return for documents', $documents);

        $this->assertStringContainsString('person=borrower', $screening);
        $this->assertStringContainsString('Open guarantor file', $screening);

        $committeeApp = $this->application($admin, 'pre_approval');
        $committee = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $committeeApp, 'workspace' => 'decision']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Committee workspace', $committee);
        $this->assertStringContainsString('Sprint critical areas on the same evidence', $committee);
        $this->assertStringContainsString('Committee sprint', $committee);
        $this->assertStringContainsString('Critical areas — not a full re-screen', $committee);
        $this->assertStringContainsString('Change with reason', $committee);
        $this->assertStringContainsString('Record the committee decision', $committee);
        $this->assertStringContainsString('Validate screening', $committee);
        $this->assertStringContainsString('Reason for approval', $committee);
        $this->assertStringContainsString('Rejection reasons', $committee);
        $this->assertStringContainsString('Borrower CRB · Guarantor · Screening', $committee);
        $this->assertStringContainsString('Sprint critical areas', $committee);

        $checklistFlags = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'checklist']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Review checklist', $checklistFlags);
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
        $this->assertStringContainsString('Management spine', $html);
        $this->assertStringContainsString('Offer · Fees · Destination · Contract · Disbursement', $html);
        $this->assertStringNotContainsString('Profile sections', $html);
        $this->assertStringNotContainsString('Borrower CRB', $html);
        $this->assertStringNotContainsString('Capital partner funds', $html);
        $this->assertStringNotContainsString('Override amount', $html);
        $this->assertStringNotContainsString('Waive fee', $html);
    }

    public function test_decision_desk_points_to_docs_and_marks_unstaged_recommendation_as_draft(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'screening');

        foreach (['National ID (front)', 'Business licence', 'Proof of residence'] as $label) {
            LoanApplicationDocumentRequest::create([
                'loan_application_id' => $app->id,
                'requested_by' => $admin->id,
                'label' => $label,
                'type' => 'document',
                'status' => 'pending',
            ]);
        }

        $decision = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'decision']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Cannot push to committee yet', $decision);
        $this->assertStringContainsString('Review checklist → Docs', $decision);
        $this->assertStringContainsString('Cannot approve until these documents are in', $decision);
        $this->assertStringContainsString('National ID (front)', $decision);
        $this->assertStringContainsString('docs_panel=requests', $decision);
        $this->assertStringContainsString('capacity_tab=documents', $decision);
        $this->assertStringNotContainsString('Who you are reviewing', $decision);

        $app->forceFill([
            'recommendation_type' => 'approve',
            'recommended_amount' => 800_000,
            'recommended_at' => now(),
            'recommended_by' => $admin->id,
        ])->save();

        $draft = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'decision']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Draft · not pushed', $draft);
        $this->assertStringContainsString('Draft on file — not pushed to committee', $draft);
        $this->assertStringNotContainsString('Recommendation on file:', $draft);
    }

    public function test_rejected_individual_application_show_page_loads(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'rejected');
        $app->update(['status' => 'rejected']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->assertSee($app->application_number, false)
            ->assertSee('Facility summary', false);
    }
}
