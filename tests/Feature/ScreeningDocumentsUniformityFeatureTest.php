<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanGroup;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\LoanProductRequirement;
use App\Models\User;
use App\Services\ApplicationDocumentRequestService;
use App\Services\LoanApplicationReviewService;
use App\Services\LoanApplicationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreeningDocumentsUniformityFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_requirement_stays_on_the_asked_member_and_documents_shell_is_shared(): void
    {
        $branch = Branch::create([
            'code' => 'SD'.random_int(10, 99),
            'name' => 'Screening Docs Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $product = LoanProduct::create([
            'code' => 'GL',
            'name' => 'Group Loan',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);

        foreach ([
            ['name' => 'National ID (front)', 'description' => 'Clear photo of the front side of your ID.'],
            ['name' => 'National ID (back)', 'description' => 'Clear photo of the back side of your ID.'],
            ['name' => 'Passport photo', 'description' => 'Recent passport-size photo, plain background.'],
            ['name' => 'Source of income proof', 'description' => 'Any document showing how you earn money.'],
            ['name' => '3 months bank statement', 'description' => 'Most recent 3 months of bank or mobile-money statement.'],
            ['name' => 'Income verification', 'description' => 'Bank statement OR mobile money statement (6 months).'],
        ] as $row) {
            LoanProductRequirement::create([
                'loan_product_id' => $product->id,
                'type' => 'document',
                'name' => $row['name'],
                'description' => $row['description'],
                'is_required' => true,
            ]);
        }

        $leader = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-SD-L',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Leader',
            'last_name' => 'Gaspari',
            'phone' => '255712349001',
            'branch_id' => $branch->id,
        ]);
        $asked = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-SD-A',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asked',
            'last_name' => 'Member',
            'phone' => '255712349002',
            'branch_id' => $branch->id,
        ]);
        $other = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-SD-O',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Other',
            'last_name' => 'Member',
            'phone' => '255712349003',
            'branch_id' => $branch->id,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-SD-001',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 900_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);

        $group = LoanGroup::create([
            'group_number' => 'GRP-SD-001',
            'name' => 'Screening Docs Group',
            'leader_customer_id' => $leader->id,
            'primary_application_id' => $application->id,
            'status' => 'active',
            'target_member_count' => 3,
        ]);

        LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $leader->id,
            'loan_application_id' => $application->id,
            'role' => 'leader',
            'requested_amount' => 300_000,
            'sort_order' => 1,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);
        $askedRow = LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $asked->id,
            'loan_application_id' => $application->id,
            'role' => 'member',
            'requested_amount' => 300_000,
            'sort_order' => 2,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);
        $otherRow = LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $other->id,
            'loan_application_id' => $application->id,
            'role' => 'member',
            'requested_amount' => 300_000,
            'sort_order' => 3,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);

        $application->update(['loan_group_id' => $group->id]);

        app(ApplicationDocumentRequestService::class)->create(
            $application->fresh(),
            $admin,
            'Updated Bank Statement',
            subjectKind: 'member',
            loanGroupMemberId: $askedRow->id,
        );

        $leaderHtml = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
            ]))
            ->assertOk()
            ->getContent();

        $askedHtml = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
                'review_person' => 'member',
                'review_m' => $askedRow->id,
            ]))
            ->assertOk()
            ->getContent();

        $otherHtml = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
                'review_person' => 'member',
                'review_m' => $otherRow->id,
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Outstanding requests (1)', $askedHtml);
        $this->assertStringContainsString('Updated Bank Statement', $askedHtml);
        $this->assertStringContainsString('Waiting for member', $askedHtml);
        $this->assertStringContainsString('Requested ', $askedHtml);
        $this->assertStringContainsString('Request more documents', $askedHtml);
        $this->assertStringNotContainsString('Application evidence', $askedHtml);
        $this->assertStringNotContainsString('>Library<', $askedHtml);
        $this->assertStringNotContainsString('No open requests for this person', $askedHtml);
        $this->assertStringNotContainsString('Outstanding requests', $otherHtml);
        $this->assertStringNotContainsString('No open requests for this person', $otherHtml);
        $this->assertStringNotContainsString('No open requests for this person', $leaderHtml);
        $this->assertStringNotContainsString('Application evidence', $leaderHtml);

        $leaderDocs = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'documents',
                'person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();
        $askedDocs = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'documents',
                'person' => 'member',
                'm' => $askedRow->id,
            ]))
            ->assertOk()
            ->getContent();
        $otherDocs = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'documents',
                'person' => 'member',
                'm' => $otherRow->id,
            ]))
            ->assertOk()
            ->getContent();

        foreach (['Application evidence', 'Checklist', 'Requested', 'Library', 'Request documents', 'Send a pack to the person on this screen', 'Review request', 'Send request'] as $needle) {
            $this->assertStringContainsString($needle, $leaderDocs);
            $this->assertStringContainsString($needle, $askedDocs);
            $this->assertStringContainsString($needle, $otherDocs);
        }
        foreach ([$leaderDocs, $askedDocs, $otherDocs] as $html) {
            $this->assertStringContainsString('name="intent" value="documents"', $html);
            $this->assertStringContainsString("requestStep = 'review'", $html);
            $this->assertStringContainsString('x-show="requestOpen"', $html);
        }

        $this->assertStringNotContainsString('Income verification', $leaderDocs);

        foreach ([$leaderHtml, $askedHtml, $otherHtml, $leaderDocs, $askedDocs, $otherDocs] as $html) {
            $this->assertStringNotContainsString('National ID (front)', $html);
            $this->assertStringNotContainsString('Clear photo of the front side of your ID.', $html);
            $this->assertStringNotContainsString('Recent passport-size photo, plain background.', $html);
            $this->assertStringNotContainsString('Source of income proof', $html);
            $this->assertStringNotContainsString('3 months bank statement', $html);
            $this->assertStringNotContainsString('Most recent 3 months of bank or mobile-money statement.', $html);
        }

        $decisionHtml = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'decision',
                'review_person' => 'member',
                'review_m' => $askedRow->id,
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Record the screening recommendation', $decisionHtml);
        $this->assertStringNotContainsString('Who you are reviewing', $decisionHtml);
    }

    public function test_group_constitution_and_roster_are_not_compulsory_blockers(): void
    {
        $branch = Branch::create([
            'code' => 'GP'.random_int(10, 99),
            'name' => 'Group Paper Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'code' => 'GL-PAPER',
            'name' => 'Group Paper Loan',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);

        foreach (['Group constitution', 'Group member roster', 'National ID (front)', 'Income verification'] as $name) {
            LoanProductRequirement::create([
                'loan_product_id' => $product->id,
                'type' => 'document',
                'name' => $name,
                'is_required' => true,
            ]);
        }

        $leader = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-GP-L',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Leader',
            'last_name' => 'Paper',
            'phone' => '255712349010',
            'branch_id' => $branch->id,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-GP-001',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 800_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);

        $missing = app(LoanApplicationReviewService::class)->dossier($application)['missing_documents'] ?? [];
        $blockers = app(LoanApplicationWorkflowService::class)->screeningDocumentBlockers($application);

        $this->assertNotContains('Income verification', $missing);
        $this->assertNotContains('National ID (front)', $missing);
        $this->assertNotContains('Group constitution', $missing);
        $this->assertNotContains('Group member roster', $missing);
        $this->assertNotContains('Income verification', $blockers);
        $this->assertNotContains('National ID (front)', $blockers);
        $this->assertFalse(collect($blockers)->contains(fn ($line) => str_contains((string) $line, 'Group constitution')));
        $this->assertFalse(collect($blockers)->contains(fn ($line) => str_contains((string) $line, 'Group member roster')));

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'decision',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Group constitution', $html);
        $this->assertStringNotContainsString('Group member roster', $html);
    }

    public function test_uploaded_requests_show_in_screening_inbox_and_admin_bell(): void
    {
        $branch = Branch::create([
            'code' => 'IN'.random_int(10, 99),
            'name' => 'Inbox Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'code' => 'GL-IN',
            'name' => 'Group Inbox Loan',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $leader = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-IN-L',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Leader',
            'last_name' => 'Inbox',
            'phone' => '255712349020',
            'branch_id' => $branch->id,
        ]);
        $member = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-IN-M',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asked',
            'last_name' => 'Member',
            'phone' => '255712349021',
            'branch_id' => $branch->id,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-IN-001',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 900_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);
        $group = LoanGroup::create([
            'group_number' => 'GRP-IN-001',
            'name' => 'Inbox Group',
            'leader_customer_id' => $leader->id,
            'primary_application_id' => $application->id,
            'status' => 'active',
            'target_member_count' => 2,
        ]);
        LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $leader->id,
            'loan_application_id' => $application->id,
            'role' => 'leader',
            'requested_amount' => 450_000,
            'sort_order' => 1,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);
        $memberRow = LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $member->id,
            'loan_application_id' => $application->id,
            'role' => 'member',
            'requested_amount' => 450_000,
            'sort_order' => 2,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);
        $application->update(['loan_group_id' => $group->id]);

        $docService = app(ApplicationDocumentRequestService::class);
        $collateral = $docService->create($application->fresh(), $admin, 'Add collateral asset');
        $statement = $docService->create(
            $application->fresh(),
            $admin,
            'Updated Mobile Money Statement',
            subjectKind: 'member',
            loanGroupMemberId: $memberRow->id,
        );
        $collateral->update(['status' => 'uploaded']);
        $statement->update(['status' => 'uploaded']);

        $this->assertSame('collateral', $docService->borrowerActionKind($collateral->fresh()));
        $this->assertSame('income', $docService->borrowerActionKind($statement->fresh()));
        $this->assertSame('Income / statements', $docService->screeningKindLabel($statement->fresh()));

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $application))
            ->assertOk()
            ->assertSee('id="submissions-inbox"', false)
            ->assertSee('Add collateral asset', false)
            ->assertSee('Updated Mobile Money Statement', false)
            ->assertSee('Asked Member · Member', false)
            ->assertSee('Income / statements', false)
            ->assertSee('Collateral', false)
            ->getContent();

        $this->assertStringContainsString('workspace=checklist', $html);
        $this->assertStringContainsString('open_group=collateral', $html);
        $this->assertStringContainsString('desk_phase=security', $html);
        $this->assertStringContainsString('#review-desk', $html);
        $this->assertStringContainsString('gate=income', $html);
        $this->assertStringContainsString('open_group=activity_income', $html);
        $this->assertStringContainsString('person=member', $html);
        $this->assertStringNotContainsString('tab=activity', $html);
        $this->assertStringNotContainsString('open_group=collateral#', $html);
        $this->assertStringNotContainsString(
            route('admin.loan-application-document-requests.satisfy', $collateral, false),
            $html,
        );

        $alerts = app(\App\Services\AdminAlertService::class)->alerts();
        $this->assertTrue($alerts->contains(
            fn (array $alert) => $alert['key'] === 'doc_submissions_'.$application->id && $alert['count'] === 2
        ));
    }

    public function test_national_id_is_not_a_documents_blocker_on_individual_loans(): void
    {
        $branch = Branch::create([
            'code' => 'IL'.random_int(10, 99),
            'name' => 'Installment Docs Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'code' => 'IL',
            'name' => 'Installment Loan',
            'category' => 'personal',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        foreach ([
            ['name' => 'National ID (front)', 'description' => 'Clear photo of the front side of your ID.'],
            ['name' => 'NIDA card', 'description' => 'Copy of NIDA.'],
            ['name' => 'Business licence', 'description' => 'Valid TRA / local government business licence.'],
        ] as $row) {
            LoanProductRequirement::create([
                'loan_product_id' => $product->id,
                'type' => 'document',
                'name' => $row['name'],
                'description' => $row['description'],
                'is_required' => true,
            ]);
        }
        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-IL-NIDA',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Iddi',
            'last_name' => 'Loan',
            'phone' => '255712349099',
            'branch_id' => $branch->id,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-IL-NIDA-001',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 400_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);

        $missing = app(LoanApplicationReviewService::class)->dossier($application)['missing_documents'] ?? [];
        $blockers = app(LoanApplicationWorkflowService::class)->screeningDocumentBlockers($application);

        $this->assertContains('Business licence', $missing);
        $this->assertNotContains('National ID (front)', $missing);
        $this->assertNotContains('NIDA card', $missing);
        $this->assertContains('Business licence', $blockers);
        $this->assertNotContains('National ID (front)', $blockers);
        $this->assertFalse(collect($blockers)->contains(fn ($line) => str_contains((string) $line, 'NIDA')));
    }

    public function test_marking_an_uploaded_income_file_reviewed_removes_it_from_the_inbox(): void
    {
        $branch = Branch::create([
            'code' => 'RV'.random_int(10, 99),
            'name' => 'Reviewed Inbox Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'code' => 'IL-RV',
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-RV-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asked',
            'last_name' => 'Member',
            'phone' => '255712349088',
            'branch_id' => $branch->id,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-RV-001',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 400_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);

        $type = \App\Models\DocumentType::create([
            'code' => 'mobile_money_statement',
            'name' => 'Mobile money statement',
            'is_active' => true,
        ]);
        $doc = \App\Models\CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $type->id,
            'file_path' => 'customer/'.$customer->id.'/mm.pdf',
            'status' => 'pending_review',
        ]);
        $docService = app(ApplicationDocumentRequestService::class);
        $statement = $docService->create($application, $admin, 'Updated Mobile Money Statement');
        $statement->update(['status' => 'uploaded']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $application))
            ->assertOk()
            ->assertSee('Updated Mobile Money Statement', false)
            ->assertSee('id="submissions-inbox"', false);

        app(\App\Services\ApplicationDocumentReviewService::class)
            ->verify($doc, $application->fresh(), $admin);

        $this->assertSame('satisfied', $statement->fresh()->status);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $application))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="submissions-inbox"', $html);
    }
}
