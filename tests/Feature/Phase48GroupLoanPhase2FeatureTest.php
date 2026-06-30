<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GroupMemberInvitation;
use App\Models\LoanApplication;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Services\GroupLoanMemberReviewService;
use App\Services\GroupMemberInvitationService;
use App\Services\GroupMemberProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase48GroupLoanPhase2FeatureTest extends TestCase
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

    public function test_group_member_onboarding_links_customer_on_accept(): void
    {
        $leader = Customer::create([
            'customer_number' => 'CU-P48-L',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Leader',
            'last_name'       => 'One',
            'phone'           => '255712345900',
        ]);

        $product = $this->groupProduct();

        $invitation = GroupMemberInvitation::create([
            'leader_customer_id' => $leader->id,
            'loan_product_id'    => $product->id,
            'invitee_first_name' => 'External',
            'invitee_last_name'  => 'Member',
            'invitee_phone'      => '255712345901',
            'token'              => 'test-group-invite-token-123456789012345',
            'short_code'         => 'GRPMBR01',
            'status'             => 'pending',
            'expires_at'         => now()->addDays(7),
        ]);

        $response = $this->post(route('site.group-member.accept', $invitation->token));
        $response->assertRedirect(route('site.register.borrower'));

        $invitation->refresh();
        $this->assertSame('accepted', $invitation->status);
    }

    public function test_group_member_progress_requires_completed_invitation_with_signature(): void
    {
        $leader = Customer::create([
            'customer_number'       => 'CU-P48-L2',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Leader',
            'last_name'             => 'Two',
            'phone'                 => '255712345902',
            'membership_expires_at' => now()->addYear(),
        ]);

        $member = Customer::create([
            'customer_number'       => 'CU-P48-M2',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Ready',
            'last_name'             => 'Member',
            'phone'                 => '255712345903',
            'membership_expires_at' => now()->addYear(),
            'onboarded_at'          => now(),
        ]);

        $invitation = GroupMemberInvitation::create([
            'leader_customer_id' => $leader->id,
            'loan_product_id'    => $this->groupProduct()->id,
            'customer_id'        => $member->id,
            'invitee_first_name' => 'Ready',
            'invitee_last_name'  => 'Member',
            'invitee_phone'      => '255712345903',
            'token'              => 'completed-invite-token-123456789012345678',
            'status'             => 'completed',
            'member_signature_data' => 'data:image/png;base64,abc',
            'member_signed_at'   => now(),
        ]);

        $status = app(GroupMemberProgressService::class)->statusFromInvitation($invitation);
        $this->assertSame('registration_complete', $status['key']);
        $this->assertFalse($status['complete']);
    }

    public function test_resolve_members_for_submit_requires_completed_external_member(): void
    {
        $leader = Customer::create([
            'customer_number' => 'CU-P48-L3',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Leader',
            'last_name'       => 'Three',
            'phone'           => '255712345904',
        ]);

        $invitation = GroupMemberInvitation::create([
            'leader_customer_id' => $leader->id,
            'loan_product_id'    => $this->groupProduct()->id,
            'invitee_first_name' => 'Pending',
            'invitee_last_name'  => 'Invitee',
            'invitee_phone'      => '255712345905',
            'token'              => 'pending-invite-token-123456789012345678',
            'status'             => 'accepted',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(GroupMemberInvitationService::class)->resolveMembersForSubmit($leader, [[
            'invitation_id'    => $invitation->id,
            'requested_amount' => 300_000,
        ]]);
    }

    public function test_admin_can_save_group_member_review_with_leader_feedback(): void
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);

        $leader = Customer::create([
            'customer_number' => 'CU-P48-L4',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Leader',
            'last_name'       => 'Four',
            'phone'           => '255712345906',
        ]);

        $member = Customer::create([
            'customer_number' => 'CU-P48-M4',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Member',
            'last_name'       => 'Four',
            'phone'           => '255712345907',
        ]);

        $member2 = Customer::create([
            'customer_number' => 'CU-P48-M5',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Member',
            'last_name'       => 'Five',
            'phone'           => '255712345908',
        ]);

        $product = $this->groupProduct();
        $application = LoanApplication::create([
            'customer_id'             => $leader->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P48-001',
            'status'                  => 'submitted',
            'current_stage'           => 'submitted',
            'requested_amount'        => 600_000,
            'requested_tenure_months' => 6,
        ]);

        app(\App\Services\GroupLendingService::class)->createForApplication(
            $application,
            [
                ['customer_id' => $leader->id, 'requested_amount' => 300_000, 'role' => 'leader'],
                ['customer_id' => $member->id, 'requested_amount' => 300_000, 'role' => 'member'],
                ['customer_id' => $member2->id, 'requested_amount' => 300_000, 'role' => 'member'],
            ],
            'Test group',
            'Business',
        );

        $row = LoanGroupMember::query()->where('customer_id', $member->id)->firstOrFail();

        app(GroupLoanMemberReviewService::class)->reviewMember(
            $row,
            'replacement_requested',
            'Income docs unclear',
            'Please replace this member or upload clearer income proof.',
            null,
        );

        $row->refresh();
        $this->assertSame('replacement_requested', $row->underwriting_status);
        $this->assertStringContainsString('replace', strtolower((string) $row->leader_feedback));
    }

    public function test_short_link_resolves_group_member_invitation(): void
    {
        $leader = Customer::create([
            'customer_number' => 'CU-P48-L5',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Leader',
            'last_name'       => 'Five',
            'phone'           => '255712345908',
        ]);

        $invitation = GroupMemberInvitation::create([
            'leader_customer_id' => $leader->id,
            'loan_product_id'    => $this->groupProduct()->id,
            'invitee_first_name' => 'Short',
            'invitee_last_name'  => 'Link',
            'invitee_phone'      => '255712345909',
            'token'              => 'short-link-token-1234567890123456789012',
            'short_code'         => 'GRPSHORT',
            'status'             => 'pending',
        ]);

        $response = $this->get('/g/GRPSHORT');
        $response->assertRedirect(route('site.group-member.invite', $invitation->token));
    }

    public function test_internal_member_invitation_requires_signature_for_verification(): void
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);

        $leader = Customer::create([
            'customer_number'       => 'CU-P48-L5',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Leader',
            'last_name'             => 'Five',
            'phone'                 => '255712345908',
            'membership_expires_at' => now()->addYear(),
            'onboarded_at'          => now(),
        ]);

        $member = Customer::create([
            'customer_number'       => 'CU-P48-M5',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Internal',
            'last_name'             => 'Member',
            'phone'                 => '255712345909',
            'membership_expires_at' => now()->addYear(),
            'onboarded_at'          => now(),
        ]);

        $product = $this->groupProduct();
        $service = app(GroupMemberInvitationService::class);

        $share = $service->prepareInternalInvitation($leader, $product, $member);
        $this->assertArrayHasKey('invitation_id', $share);

        $invitation = GroupMemberInvitation::find($share['invitation_id']);
        $this->assertSame('accepted', $invitation->status);
        $this->assertSame($member->id, $invitation->customer_id);

        $status = app(GroupMemberProgressService::class)->statusFromInvitation($invitation);
        $this->assertSame('registration_complete', $status['key']);
        $this->assertFalse($status['complete']);
    }

    public function test_previous_group_members_lists_prior_invitees(): void
    {
        $leader = Customer::create([
            'customer_number' => 'CU-P48-L6',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Leader',
            'last_name'       => 'Six',
            'phone'           => '255712345910',
        ]);

        $member = Customer::create([
            'customer_number' => 'CU-P48-M6',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Past',
            'last_name'       => 'Member',
            'phone'           => '255712345911',
        ]);

        GroupMemberInvitation::create([
            'leader_customer_id' => $leader->id,
            'loan_product_id'    => $this->groupProduct()->id,
            'customer_id'        => $member->id,
            'invitee_first_name' => 'Past',
            'invitee_last_name'  => 'Member',
            'invitee_phone'      => '255712345911',
            'token'              => 'past-member-token-123456789012345678901',
            'status'             => 'completed',
        ]);

        $items = app(GroupMemberInvitationService::class)->previousMembersForLeader($leader);
        $this->assertCount(1, $items);
        $this->assertSame($member->id, $items[0]['customer_id']);
    }
}
