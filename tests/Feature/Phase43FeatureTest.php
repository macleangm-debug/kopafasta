<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Services\GroupLendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase43FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_recovery_advances_through_stages(): void
    {
        \App\Models\Setting::setMany([
            'loan.group_min_members' => 2,
            'loan.group_max_members' => 10,
        ]);

        $product = LoanProduct::create([
            'code'              => 'GL',
            'name'              => 'Group Loan',
            'category'          => 'group',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 200_000,
            'max_amount'        => 2_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);

        $leader = Customer::create([
            'customer_number' => 'CU-P43-L',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Leader',
            'last_name'       => 'Recovery',
            'phone'           => '255712345870',
        ]);

        $member = Customer::create([
            'customer_number' => 'CU-P43-M',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Member',
            'last_name'       => 'Default',
            'phone'           => '255712345871',
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $leader->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P43-001',
            'status'                  => 'submitted',
            'current_stage'           => 'credit_appraisal',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 6,
        ]);

        $group = app(GroupLendingService::class)->createForApplication($application, [
            ['customer_id' => $leader->id, 'role' => 'leader'],
            ['customer_id' => $member->id],
        ], 'Recovery test group');

        $defaultingMember = LoanGroupMember::query()->where('customer_id', $member->id)->firstOrFail();
        $service = app(GroupLendingService::class);

        $group = $service->onMemberMissedPayment($defaultingMember);
        $this->assertSame('group_liability', $group->recovery_stage);

        $group = $service->onMemberMissedPayment($defaultingMember->fresh());
        $this->assertSame('external', $group->recovery_stage);
        $this->assertTrue($service->shouldEscalateToExternal($group));
    }
}
