<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\GroupContractSignatureService;
use App\Services\GroupLendingService;
use App\Services\GroupMemberReplacementService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase59GroupMemberReplacementFeatureTest extends TestCase
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
            'repayment_cadence'      => 'monthly',
            'application_fee_amount' => 10_000,
        ]);
    }

    /** @return array{application: LoanApplication, leader: Customer, declinedMember: LoanGroupMember, replacement: Customer} */
    protected function declinedMemberFixture(): array
    {
        Setting::setMany(['loan.group_min_members' => 2, 'loan.group_max_members' => 10]);

        $leader = Customer::create([
            'customer_number'       => 'CU-P59-L',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Leader',
            'last_name'             => 'P59',
            'phone'                 => '255712345930',
            'membership_expires_at' => now()->addYear(),
        ]);

        $declined = Customer::create([
            'customer_number'       => 'CU-P59-D',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Declined',
            'last_name'             => 'Member',
            'phone'                 => '255712345931',
            'membership_expires_at' => now()->addYear(),
        ]);

        $replacement = Customer::create([
            'customer_number'       => 'CU-P59-R',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Replacement',
            'last_name'             => 'Member',
            'phone'                 => '255712345932',
            'membership_expires_at' => now()->addYear(),
            'member_no'             => 'KPF-TZ-P59REPL',
        ]);

        $product = $this->groupProduct();

        $application = LoanApplication::create([
            'customer_id'             => $leader->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P59-001',
            'status'                  => 'approved',
            'current_stage'           => 'disbursement',
            'offer_status'            => 'accepted',
            'requested_amount'        => 600_000,
            'requested_tenure_months' => 6,
        ]);

        app(GroupLendingService::class)->createForApplication(
            $application,
            [
                ['customer_id' => $leader->id, 'requested_amount' => 300_000, 'role' => 'leader'],
                ['customer_id' => $declined->id, 'requested_amount' => 300_000, 'role' => 'member'],
            ],
            'P59 Group',
            'Business',
        );

        $declinedMember = LoanGroupMember::query()
            ->where('loan_group_id', $application->loan_group_id)
            ->where('customer_id', $declined->id)
            ->firstOrFail();

        $declinedMember->update([
            'contract_signature_status' => 'declined',
            'contract_declined_at'      => now(),
            'contract_decline_reason'   => 'Not interested',
        ]);

        LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id'         => $leader->id,
            'document_type'       => 'loan_contract',
            'reference'           => 'LC-P59-001',
            'status'              => 'signed',
            'signed_at'           => now(),
        ]);

        app(GroupContractSignatureService::class)->syncLeaderFromContract($application->fresh());

        return [
            'application'    => $application->fresh(),
            'leader'         => $leader,
            'declinedMember' => $declinedMember->fresh(),
            'replacement'    => $replacement,
        ];
    }

    public function test_leader_dashboard_shows_contract_progress_and_replaceable_member(): void
    {
        ['application' => $application, 'leader' => $leader] = $this->declinedMemberFixture();

        $dashboard = app(GroupMemberReplacementService::class)->leaderDashboard($application, $leader);

        $this->assertNotNull($dashboard);
        $this->assertFalse($dashboard['all_signed']);
        $this->assertTrue($dashboard['can_replace']);
        $this->assertCount(1, $dashboard['replaceable']);
    }

    public function test_leader_can_replace_declined_member_with_internal_member(): void
    {
        ['application' => $application, 'leader' => $leader, 'declinedMember' => $oldMember, 'replacement' => $replacement] = $this->declinedMemberFixture();

        $user = User::factory()->create(['role' => 'borrower']);
        $leader->update(['user_id' => $user->id]);
        app(PinService::class)->setPin($user, '1234');

        $this->actingAs($user)
            ->postJson(route('site.borrower.group-member.replace-internal', [$application, $oldMember]), [
                'member_no' => 'P59REPL',
                'phone'     => '0712345932',
                'name'      => 'Replacement Member',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('loan_group_members', [
            'id'            => $oldMember->id,
            'member_status' => 'replaced',
        ]);

        $this->assertDatabaseHas('loan_group_members', [
            'loan_group_id'             => $application->loan_group_id,
            'customer_id'               => $replacement->id,
            'member_status'             => 'active',
            'contract_signature_status' => 'pending',
        ]);
    }

    public function test_replaced_members_excluded_from_contract_progress(): void
    {
        ['application' => $application, 'leader' => $leader, 'declinedMember' => $oldMember, 'replacement' => $replacement] = $this->declinedMemberFixture();

        app(GroupMemberReplacementService::class)->replaceWithInternalMember(
            $application,
            $leader,
            $oldMember,
            $replacement,
        );

        $progress = app(GroupContractSignatureService::class)->progress($application->fresh());

        $this->assertSame(2, $progress['target']);
        $this->assertSame(1, $progress['signed']);
        $this->assertSame(1, $progress['pending']);
        $this->assertFalse($progress['all_signed']);
    }
}
