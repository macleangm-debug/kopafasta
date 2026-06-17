<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Services\GroupLendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase42FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_lending_creates_leader_first_unlock_queue(): void
    {
        Setting::setMany([
            'loan.group_min_members' => 3,
            'loan.group_max_members' => 10,
            'loan.group_leader_unlock_repayments' => 2,
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
            'application_fee_amount' => 10_000,
        ]);

        $leader = Customer::create([
            'customer_number' => 'CU-P42-L',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Leader',
            'last_name'       => 'Member',
            'phone'           => '255712345860',
        ]);

        $memberTwo = Customer::create([
            'customer_number' => 'CU-P42-2',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Second',
            'last_name'       => 'Member',
            'phone'           => '255712345861',
        ]);

        $memberThree = Customer::create([
            'customer_number' => 'CU-P42-3',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Third',
            'last_name'       => 'Member',
            'phone'           => '255712345862',
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $leader->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P42-001',
            'status'                  => 'submitted',
            'current_stage'           => 'credit_appraisal',
            'requested_amount'        => 900_000,
            'requested_tenure_months' => 6,
        ]);

        $group = app(GroupLendingService::class)->createForApplication($application, [
            ['customer_id' => $leader->id, 'role' => 'leader'],
            ['customer_id' => $memberTwo->id],
            ['customer_id' => $memberThree->id],
        ], 'Village banking group');

        $this->assertSame('GRP-', substr($group->group_number, 0, 4));
        $this->assertDatabaseHas('loan_applications', [
            'id' => $application->id,
            'loan_group_id' => $group->id,
        ]);

        $leaderRow = LoanGroupMember::query()->where('loan_group_id', $group->id)->where('role', 'leader')->first();
        $locked = LoanGroupMember::query()->where('loan_group_id', $group->id)->where('disbursement_status', 'locked')->count();

        $this->assertSame('unlocked', $leaderRow->disbursement_status);
        $this->assertSame(2, $locked);
        $this->assertTrue(app(GroupLendingService::class)->canDisburseMember($leaderRow));
    }

    public function test_leader_repayments_unlock_next_member(): void
    {
        Setting::setMany([
            'loan.group_leader_unlock_repayments' => 2,
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
            'customer_number' => 'CU-P42-L2',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Leader',
            'last_name'       => 'Two',
            'phone'           => '255712345863',
        ]);

        $memberTwo = Customer::create([
            'customer_number' => 'CU-P42-22',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Next',
            'last_name'       => 'Member',
            'phone'           => '255712345864',
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $leader->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P42-002',
            'status'                  => 'submitted',
            'current_stage'           => 'credit_appraisal',
            'requested_amount'        => 600_000,
            'requested_tenure_months' => 6,
        ]);

        $group = app(GroupLendingService::class)->createForApplication($application, [
            ['customer_id' => $leader->id, 'role' => 'leader'],
            ['customer_id' => $memberTwo->id],
        ]);

        $leaderMember = $group->members->firstWhere('role', 'leader');
        $loan = Loan::create([
            'customer_id'         => $leader->id,
            'loan_product_id'     => $product->id,
            'loan_application_id' => $application->id,
            'loan_number'         => 'LN-P42-002',
            'principal_amount'    => 300_000,
            'approved_amount'     => 300_000,
            'interest_rate'       => 0.15,
            'tenure_months'       => 6,
            'outstanding_balance' => 250_000,
            'status'              => 'active',
        ]);

        $leaderMember->update(['loan_id' => $loan->id]);

        $service = app(GroupLendingService::class);
        $service->recordSuccessfulRepayment($loan);
        $this->assertSame('locked', $group->members()->where('customer_id', $memberTwo->id)->value('disbursement_status'));

        $service->recordSuccessfulRepayment($loan->fresh());
        $this->assertSame('unlocked', $group->members()->where('customer_id', $memberTwo->id)->value('disbursement_status'));
    }

    public function test_group_application_fee_multiplies_by_member_count(): void
    {
        Setting::setMany([
            'loan.group_application_fee_per_member' => true,
            'loan.group_min_members' => 5,
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
            'application_fee_amount' => 10_000,
        ]);

        $fee = app(GroupLendingService::class)->quotedApplicationFee(null, $product, 5);

        $this->assertSame(50_000, $fee);
    }
}
