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

        $shellStrings = [
            'Application evidence',
            'Checklist',
            'Requested',
            'Library',
            'Request documents',
            'National ID (front)',
            'Send a pack to the person on this screen',
        ];

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

        foreach ($shellStrings as $needle) {
            $this->assertStringContainsString($needle, $leaderHtml);
            $this->assertStringContainsString($needle, $askedHtml);
            $this->assertStringContainsString($needle, $otherHtml);
        }

        $this->assertStringContainsString('Income verification', $leaderHtml);
        $this->assertStringContainsString('Bank statement OR mobile money statement (6 months).', $leaderHtml);
        $this->assertStringContainsString('Bank statement OR mobile money statement (6 months).', $askedHtml);
        $this->assertStringNotContainsString('Bank statement OR mobile money statement (6 months).', $otherHtml);
        $this->assertStringContainsString('No open requests for this person', $otherHtml);
        $this->assertStringNotContainsString('No open requests for this person', $askedHtml);
        $this->assertStringContainsString('Waiting on', $askedHtml);

        foreach ([$leaderHtml, $askedHtml, $otherHtml] as $html) {
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

        foreach (['Group constitution', 'Group member roster', 'National ID (front)'] as $name) {
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

        $this->assertContains('National ID (front)', $missing);
        $this->assertNotContains('Group constitution', $missing);
        $this->assertNotContains('Group member roster', $missing);
        $this->assertContains('National ID (front)', $blockers);
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
        $this->assertSame('Income verification', $docService->screeningKindLabel($statement->fresh()));

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $application))
            ->assertOk()
            ->assertSee('id="submissions-inbox"', false)
            ->assertSee('Add collateral asset', false)
            ->assertSee('Updated Mobile Money Statement', false)
            ->assertSee('Asked Member · Member', false)
            ->assertSee('Income verification', false)
            ->assertSee('Collateral', false)
            ->getContent();

        $this->assertStringContainsString('tab=collateral', $html);
        $this->assertStringContainsString('tab=activity', $html);
        $this->assertStringContainsString('person=member', $html);

        $alerts = app(\App\Services\AdminAlertService::class)->alerts();
        $this->assertTrue($alerts->contains(
            fn (array $alert) => $alert['key'] === 'doc_submissions_'.$application->id && $alert['count'] === 2
        ));
    }
}
