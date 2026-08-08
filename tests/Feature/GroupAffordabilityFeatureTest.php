<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanGroup;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Services\GroupAffordabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupAffordabilityFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_evaluation_flags_members_who_fail_capacity(): void
    {
        $leader = Customer::create([
            'customer_number' => 'CU-GRP-L',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Leader',
            'last_name' => 'Strong',
            'phone' => '255712341001',
            'monthly_income' => 3_000_000,
        ]);

        $weak = Customer::create([
            'customer_number' => 'CU-GRP-W',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Weak',
            'last_name' => 'Member',
            'phone' => '255712341002',
            'monthly_income' => 50_000,
        ]);

        $product = LoanProduct::create([
            'code' => 'GL',
            'name' => 'Group Loan',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 100_000,
            'max_amount' => 10_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GRP-001',
            'requested_amount' => 2_000_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);

        $group = LoanGroup::create([
            'group_number' => 'GRP-TEST-001',
            'name' => 'Test Group',
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
            'requested_amount' => 1_000_000,
            'sort_order' => 1,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);

        LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $weak->id,
            'role' => 'member',
            'requested_amount' => 1_000_000,
            'sort_order' => 2,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);

        $application->update(['loan_group_id' => $group->id]);

        $result = app(GroupAffordabilityService::class)->evaluate($application->fresh([
            'customer', 'product', 'loanGroup.members.customer',
        ]));

        $this->assertTrue($result['is_group']);
        $this->assertSame('fail', $result['verdict']);
        $this->assertNotEmpty($result['failed_members']);
        $this->assertTrue(
            collect($result['failed_members'])->contains(fn ($m) => str_contains((string) ($m['name'] ?? ''), 'Weak'))
        );
    }
}
