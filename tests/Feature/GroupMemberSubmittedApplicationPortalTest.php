<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\LoanGroup;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\BorrowerApplicationsDashboardService;
use App\Services\GroupApplicationStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupMemberSubmittedApplicationPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_group_member_sees_the_live_file_and_document_request(): void
    {
        $product = LoanProduct::create([
            'code' => 'GL-PORTAL',
            'name' => 'Group Loan',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $leaderUser = User::factory()->create(['role' => 'borrower']);
        $memberUser = User::factory()->create(['role' => 'borrower']);
        $leader = Customer::create([
            'user_id' => $leaderUser->id,
            'customer_number' => 'CU-GL-L',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Gaspari',
            'last_name' => 'Leader',
            'phone' => '255900000101',
        ]);
        $member = Customer::create([
            'user_id' => $memberUser->id,
            'customer_number' => 'CU-GL-M',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Rogathe',
            'last_name' => 'Member',
            'phone' => '255900000102',
        ]);
        $application = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GL-PORTAL',
            'status' => 'pending_documents',
            'current_stage' => 'screening',
            'requested_amount' => 800_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);
        $group = LoanGroup::create([
            'group_number' => 'GRP-GL-PORTAL',
            'name' => 'Nyella Group',
            'leader_customer_id' => $leader->id,
            'primary_application_id' => $application->id,
            'status' => 'active',
            'application_status' => 'under_review',
            'target_member_count' => 2,
        ]);
        $application->update(['loan_group_id' => $group->id]);
        LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $leader->id,
            'loan_application_id' => $application->id,
            'role' => 'leader',
            'member_status' => 'active',
            'onboarding_status' => 'complete',
            'requested_amount' => 400_000,
        ]);
        LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $member->id,
            'loan_application_id' => null,
            'role' => 'member',
            'member_status' => 'active',
            'onboarding_status' => 'complete',
            'requested_amount' => 200_000,
        ]);
        LoanApplicationDocumentRequest::create([
            'loan_application_id' => $application->id,
            'subject_customer_id' => $member->id,
            'subject_kind' => 'member',
            'type' => 'document',
            'label' => 'Updated Bank Statement',
            'status' => 'pending',
        ]);

        $rows = app(BorrowerApplicationsDashboardService::class)->applicationsForCustomer($member);
        $row = collect($rows)->firstWhere('application_number', 'APP-GL-PORTAL');

        $this->assertNotNull($row);
        $this->assertFalse($row['is_closed'] ?? true);
        $this->assertSame('documents_requested', $row['status']);
        $this->assertEquals(200_000.0, $row['requested_amount']);
        $this->assertSame(route('site.borrower.application', $application->id), $row['action_url']);
        $this->assertNotEmpty($row['underwriting_actions'] ?? []);

        $groupStatus = app(GroupApplicationStatusService::class)->resolveForGroup($group->fresh(), $application->fresh());
        $this->assertSame('documents_requested', $groupStatus['key']);
        $this->assertSame('Documents requested', $groupStatus['label']);
    }
}
