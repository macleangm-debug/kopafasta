<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GroupMemberInvitation;
use App\Models\LoanApplication;
use App\Models\LoanGroup;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Services\GroupApplyService;
use App\Services\GroupLendingService;
use App\Services\LoanPolicyService;
use App\Services\SmartLoanApplicationWizardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase47FeatureTest extends TestCase
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
            'min_amount'             => 200_000,
            'max_amount'             => 2_000_000,
            'tenure_min_months'      => 3,
            'tenure_max_months'      => 12,
            'application_fee_amount' => 10_000,
        ]);
    }

    public function test_group_wizard_step_plan_includes_group_steps(): void
    {
        $product = $this->groupProduct();
        $customer = Customer::create([
            'customer_number' => 'CU-P47-PLAN',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Plan',
            'last_name'       => 'Borrower',
            'phone'           => '255712345890',
        ]);

        $steps = app(SmartLoanApplicationWizardService::class)->borrowerStepPlan($customer, $product);
        $keys = collect($steps)->pluck('key')->all();

        $this->assertContains('group_setup', $keys);
        $this->assertContains('group_members', $keys);
        $this->assertNotContains('guarantor', $keys);
    }

    public function test_group_products_skip_guarantor_requirement(): void
    {
        $product = $this->groupProduct();
        $product->update(['requires_guarantor' => true]);

        $this->assertFalse(
            app(LoanPolicyService::class)->requiresGuarantorForApplication($product, 500_000)
        );
    }

    public function test_group_member_lookup_by_membership_and_phone(): void
    {
        $leader = Customer::create([
            'customer_number'       => 'CU-P47-L',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Group',
            'last_name'             => 'Leader',
            'phone'                 => '255712345880',
            'membership_expires_at' => now()->addYear(),
            'member_no'             => 'KPF-TZ-P47LEAD',
        ]);

        $member = Customer::create([
            'customer_number'       => 'CU-P47-M',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Group',
            'last_name'             => 'Member',
            'phone'                 => '255712345881',
            'membership_expires_at' => now()->addYear(),
            'member_no'             => 'KPF-TZ-P47MEMB',
        ]);

        $service = app(GroupApplyService::class);

        $found = $service->lookupMemberByMembershipAndPhone($leader, 'P47MEMB', '0712345881', 'Group Member');
        $this->assertTrue($found['ok']);
        $this->assertSame($member->id, $found['customer_id']);

        $self = $service->lookupMemberByMembershipAndPhone($leader, 'P47LEAD', '0712345880');
        $this->assertFalse($self['ok']);
    }

    public function test_group_apply_service_validates_and_creates_group_on_submit_payload(): void
    {
        Setting::setMany([
            'loan.group_min_members' => 3,
            'loan.group_max_members' => 10,
        ]);

        $product = $this->groupProduct();

        $leader = Customer::create([
            'customer_number' => 'CU-P47-L2',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Leader',
            'last_name'       => 'Apply',
            'phone'           => '255712345882',
        ]);

        $member = Customer::create([
            'customer_number' => 'CU-P47-M2',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Member',
            'last_name'       => 'Apply',
            'phone'           => '255712345883',
        ]);

        $member2 = Customer::create([
            'customer_number' => 'CU-P47-M3',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Member',
            'last_name'       => 'Three',
            'phone'           => '255712345884',
        ]);

        $invitation = GroupMemberInvitation::create([
            'leader_customer_id' => $leader->id,
            'loan_product_id'    => $product->id,
            'customer_id'        => $member->id,
            'invitee_first_name' => 'Member',
            'invitee_last_name'  => 'Apply',
            'invitee_phone'      => '255712345883',
            'token'              => 'p47-member-invite-token-123456789012345678',
            'status'             => 'completed',
            'member_signature_data' => 'data:image/png;base64,abc',
            'member_signed_at'   => now(),
        ]);

        $invitation2 = GroupMemberInvitation::create([
            'leader_customer_id' => $leader->id,
            'loan_product_id'    => $product->id,
            'customer_id'        => $member2->id,
            'invitee_first_name' => 'Member',
            'invitee_last_name'  => 'Three',
            'invitee_phone'      => '255712345884',
            'token'              => 'p47-member-invite-token-223456789012345678',
            'status'             => 'completed',
            'member_signature_data' => 'data:image/png;base64,abc',
            'member_signed_at'   => now(),
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $leader->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P47-001',
            'status'                  => 'submitted',
            'current_stage'           => 'submitted',
            'requested_amount'        => 600_000,
            'requested_tenure_months' => 6,
        ]);

        $validated = app(GroupApplyService::class)->validateGroupPayload($leader, $product, [
            'name'                => 'VICOBA group',
            'purpose'             => 'business',
            'target_member_count' => 3,
            'amount_per_member'   => 300_000,
            'members' => [
                ['customer_id' => $leader->id, 'requested_amount' => 300_000],
                ['customer_id' => $member->id, 'invitation_id' => $invitation->id, 'requested_amount' => 300_000],
                ['customer_id' => $member2->id, 'invitation_id' => $invitation2->id, 'requested_amount' => 300_000],
            ],
        ]);

        $this->assertSame('VICOBA group', $validated['name']);
        $this->assertSame('business', $validated['purpose']);
        $this->assertCount(3, $validated['members']);

        $group = app(GroupLendingService::class)->createForApplication(
            $application,
            $validated['members'],
            $validated['name'],
            'Business expansion',
        );

        $this->assertInstanceOf(LoanGroup::class, $group);
        $this->assertDatabaseHas('loan_groups', [
            'id'      => $group->id,
            'name'    => 'VICOBA group',
            'purpose' => 'Business expansion',
        ]);

        $leaderRow = LoanGroupMember::query()
            ->where('loan_group_id', $group->id)
            ->where('role', 'leader')
            ->first();

        $this->assertSame('300000.00', number_format((float) $leaderRow->requested_amount, 2, '.', ''));
    }

    public function test_application_fee_scales_with_member_count(): void
    {
        Setting::setMany([
            'loan.group_application_fee_per_member' => true,
        ]);

        $product = $this->groupProduct();
        $customer = Customer::create([
            'customer_number'       => 'CU-P47-FEE',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Fee',
            'last_name'             => 'Quote',
            'phone'                 => '255712345884',
            'membership_expires_at' => now()->addYear(),
        ]);

        $service = app(GroupLendingService::class);
        $single = $service->quotedApplicationFee($customer, $product, 1);
        $triple = $service->quotedApplicationFee($customer, $product, 3);

        $this->assertSame($single * 3, $triple);
    }
}
