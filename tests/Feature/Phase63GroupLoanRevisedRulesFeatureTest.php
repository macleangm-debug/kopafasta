<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GroupMemberInvitation;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Services\GroupLendingService;
use App\Services\GroupMemberProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase63GroupLoanRevisedRulesFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function groupProduct(): LoanProduct
    {
        return LoanProduct::create([
            'code'                   => 'GL',
            'name'                   => 'Group Loan',
            'category'               => 'group',
            'is_active'              => true,
            'interest_rate'          => 0.15,
            'min_amount'             => 400_000,
            'max_amount'             => 10_000_000,
            'tenure_min_months'      => 3,
            'tenure_max_months'      => 12,
            'application_fee_amount' => 10_000,
        ]);
    }

    public function test_group_tenure_options_are_discrete_monthly_choices(): void
    {
        $product = $this->groupProduct();
        $options = app(GroupLendingService::class)->tenureOptions($product);

        $this->assertSame([3, 6, 9, 12], $options);
    }

    public function test_application_fee_breakdown_uses_target_member_count(): void
    {
        $product = $this->groupProduct();
        $customer = Customer::create([
            'customer_number'       => 'CU-P63-FEE',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Fee',
            'last_name'             => 'Leader',
            'phone'                 => '255712345990',
            'membership_expires_at' => now()->addYear(),
        ]);

        $breakdown = app(GroupLendingService::class)->applicationFeeBreakdown($customer, $product, 8);

        $this->assertSame(10_000, $breakdown['per_member']);
        $this->assertSame(8, $breakdown['member_count']);
        $this->assertSame(80_000, $breakdown['total']);
    }

    public function test_group_progress_blocks_submit_until_all_kyc_complete(): void
    {
        Setting::setMany([
            'loan.group_min_members' => 3,
            'loan.group_max_members' => 10,
        ]);

        $leader = Customer::create([
            'customer_number'       => 'CU-P63-L',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Leader',
            'last_name'             => 'Group',
            'phone'                 => '255712345991',
            'membership_expires_at' => now()->addYear(),
            'onboarded_at'          => now(),
        ]);

        $invitation = GroupMemberInvitation::create([
            'leader_customer_id' => $leader->id,
            'loan_product_id'    => $this->groupProduct()->id,
            'invitee_first_name' => 'Pending',
            'invitee_last_name'  => 'Member',
            'invitee_phone'      => '255712345992',
            'token'              => 'p63-pending-invite-token-123456789012345678',
            'status'             => 'pending',
            'expires_at'         => now()->addDays(7),
        ]);

        $progress = app(GroupMemberProgressService::class)->summarize([
            [
                'customer_id'       => $leader->id,
                'role'              => 'leader',
                'requested_amount'  => 500_000,
            ],
            [
                'invitation_id'     => $invitation->id,
                'name'              => 'Pending Member',
                'phone'             => '255712345992',
                'requested_amount'  => 500_000,
            ],
        ], 2);

        $this->assertFalse($progress['can_submit']);
        $this->assertGreaterThanOrEqual(1, $progress['invitations_pending']);
        $this->assertContains('invitation_sent', collect($progress['members'])->pluck('status_key')->all());
    }

    public function test_group_application_fee_always_multiplies_by_members(): void
    {
        $product = $this->groupProduct();

        Setting::setMany([
            'loan.group_application_fee_per_member' => false,
        ]);

        $fee = app(GroupLendingService::class)->quotedApplicationFee(null, $product, 8);

        $this->assertSame(80_000, $fee);
    }
}
