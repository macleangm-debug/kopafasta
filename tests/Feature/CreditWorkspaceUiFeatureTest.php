<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\LoanGroup;
use App\Models\LoanGroupMember;
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
            ->assertSee('Credit file', false)
            ->assertSee('Facility summary', false)
            ->assertSee('View only', false)
            ->assertSee('Closed file', false)
            ->assertDontSee('Edit application')
            ->assertDontSee('What you need to decide');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.edit', $app))
            ->assertForbidden();
    }

    public function test_withdrawn_file_shows_withdrawn_status_only(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'rejected');
        $app->update(['status' => 'withdrawn', 'current_stage' => 'rejected']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->assertSee('Withdrawn', false)
            ->assertSee('View only', false)
            ->assertDontSee('Edit application');
    }

    public function test_disbursed_and_arrears_files_use_credit_management_tabs(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'disbursement');
        $app->update(['status' => 'disbursed', 'disbursed_at' => now()->subMonths(2)]);

        $loan = Loan::create([
            'customer_id'         => $app->customer_id,
            'loan_product_id'     => $app->loan_product_id,
            'loan_application_id' => $app->id,
            'loan_number'         => 'LN-CW-ARRS',
            'principal_amount'    => 50_000,
            'approved_amount'     => 50_000,
            'outstanding_balance' => 79_934,
            'interest_rate'       => 0.18,
            'tenure_months'       => 2,
            'status'              => 'arrears',
            'disbursement_date'   => now()->subMonths(2),
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Credit management', $html);
        $this->assertStringContainsString('Facility', $html);
        $this->assertStringContainsString('Review checklist', $html);
        $this->assertStringContainsString('Profiles', $html);
        $this->assertStringContainsString('Loan in arrears', $html);
        $this->assertStringContainsString($loan->loan_number, $html);
        $this->assertStringNotContainsString('What you need to decide', $html);
        $this->assertStringNotContainsString('>Decision</a>', $html);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $loan))
            ->assertRedirect(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'workspace' => 'facility',
            ]));
    }

    public function test_disbursed_group_application_show_page_loads(): void
    {
        $admin = $this->staff();
        $product = LoanProduct::create([
            'code'              => 'GL-CW-'.random_int(100, 999),
            'name'              => 'Group Loan',
            'category'          => 'group',
            'is_active'         => true,
            'interest_rate'     => 0.18,
            'min_amount'        => 50_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);
        $leader = Customer::create([
            'user_id'         => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-GL-L-'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Gaspari',
            'last_name'       => 'Shiliba',
            'phone'           => '25571'.random_int(1000000, 9999999),
            'branch_id'       => $admin->branch_id,
            'monthly_income'  => 400_000,
        ]);
        $app = LoanApplication::create([
            'customer_id'             => $leader->id,
            'loan_product_id'         => $product->id,
            'branch_id'               => $admin->branch_id,
            'application_number'      => 'APP-GL-C74S',
            'requested_amount'        => 150_000,
            'requested_tenure_months' => 2,
            'status'                  => 'disbursed',
            'current_stage'           => 'disbursement',
            'submitted_at'            => now()->subMonths(2),
            'disbursed_at'            => now()->subMonths(2),
        ]);
        $group = LoanGroup::create([
            'group_number'           => 'GRP-CW-001',
            'name'                   => 'Demo Group',
            'leader_customer_id'     => $leader->id,
            'primary_application_id' => $app->id,
            'status'                 => 'active',
            'target_member_count'    => 1,
        ]);
        LoanGroupMember::create([
            'loan_group_id'        => $group->id,
            'customer_id'          => $leader->id,
            'loan_application_id'  => $app->id,
            'role'                 => 'leader',
            'requested_amount'     => 50_000,
            'sort_order'           => 1,
            'onboarding_status'    => 'complete',
            'underwriting_status'  => 'pending',
        ]);
        $app->update(['loan_group_id' => $group->id]);
        Loan::create([
            'customer_id'         => $leader->id,
            'loan_product_id'     => $product->id,
            'loan_application_id' => $app->id,
            'loan_number'         => 'LN-GL-X6C8',
            'principal_amount'    => 50_000,
            'approved_amount'     => 50_000,
            'outstanding_balance' => 79_934,
            'interest_rate'       => 0.18,
            'tenure_months'       => 2,
            'status'              => 'arrears',
            'disbursement_date'   => now()->subMonths(2),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->assertSee('Credit management', false)
            ->assertSee('LN-GL-X6C8', false)
            ->assertSee('Facility', false)
            ->assertSee('Review checklist', false)
            ->assertSee('Profiles', false);
    }

    public function test_checklist_affordability_survives_stale_payload_without_pass_key(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'screening');
        $app->update([
            'credit_appraisal_payload' => [
                'affordability' => [
                    'reason' => 'Stale stored row with no pass flag',
                    'net_income' => 300_000,
                ],
            ],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'workspace' => 'checklist',
                'capacity_tab' => 'affordability',
            ]))
            ->assertOk()
            ->assertSee('Affordability', false)
            ->assertDontSee('Undefined array key', false);
    }
}
