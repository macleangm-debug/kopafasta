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
    }
}
