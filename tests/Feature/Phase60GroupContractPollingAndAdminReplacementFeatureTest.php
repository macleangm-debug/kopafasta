<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\GroupLendingService;
use App\Services\GroupMemberReplacementService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase60GroupContractPollingAndAdminReplacementFeatureTest extends TestCase
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

    /** @return array{application: LoanApplication, leader: Customer, member: LoanGroupMember} */
    protected function groupFixture(): array
    {
        Setting::setMany(['loan.group_min_members' => 2, 'loan.group_max_members' => 10]);

        $leader = Customer::create([
            'customer_number'       => 'CU-P60-L',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Leader',
            'last_name'             => 'P60',
            'phone'                 => '255712345940',
            'membership_expires_at' => now()->addYear(),
        ]);

        $member = Customer::create([
            'customer_number'       => 'CU-P60-M',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Member',
            'last_name'             => 'P60',
            'phone'                 => '255712345941',
            'membership_expires_at' => now()->addYear(),
        ]);

        $product = $this->groupProduct();

        $application = LoanApplication::create([
            'customer_id'             => $leader->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P60-001',
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
                ['customer_id' => $member->id, 'requested_amount' => 300_000, 'role' => 'member'],
            ],
            'P60 Group',
            'Business',
        );

        $memberRow = LoanGroupMember::query()
            ->where('loan_group_id', $application->loan_group_id)
            ->where('customer_id', $member->id)
            ->firstOrFail();

        return [
            'application' => $application->fresh(),
            'leader'      => $leader,
            'member'      => $memberRow,
        ];
    }

    public function test_leader_can_poll_contract_progress_json(): void
    {
        ['application' => $application, 'leader' => $leader] = $this->groupFixture();

        $user = User::factory()->create(['role' => 'borrower']);
        $leader->update(['user_id' => $user->id]);
        app(PinService::class)->setPin($user, '1234');

        $this->actingAs($user)
            ->getJson(route('site.borrower.group-contract.progress', $application))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('progress.target', 2)
            ->assertJsonPath('progress.pending', 2);
    }

    public function test_admin_can_request_member_replacement(): void
    {
        ['application' => $application, 'member' => $memberRow] = $this->groupFixture();

        $officer = User::factory()->create(['role' => 'officer']);

        $this->actingAs($officer);
        $this->actingAs($officer, 'admin')
            ->post(route('admin.loan-applications.request-group-member-replacement', [$application, $memberRow]), [
                'reason' => 'CRB score below threshold.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('loan_group_members', [
            'id'                  => $memberRow->id,
            'underwriting_status' => 'replacement_requested',
        ]);

        $dashboard = app(GroupMemberReplacementService::class)->leaderDashboard(
            $application->fresh(),
            $application->customer,
        );

        $this->assertTrue($dashboard['can_replace']);
        $this->assertCount(1, $dashboard['replaceable']);
    }

    public function test_admin_can_poll_group_contract_progress_json(): void
    {
        ['application' => $application] = $this->groupFixture();

        $officer = User::factory()->create(['role' => 'officer']);

        $this->actingAs($officer);
        $this->actingAs($officer, 'admin')
            ->getJson(route('admin.loan-applications.group-contract-progress', $application))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('contract_signatures.target', 2);
    }
}
