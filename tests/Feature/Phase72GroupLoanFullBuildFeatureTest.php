<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GroupMemberInvitation;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\GroupLendingService;
use App\Services\LoanApplicationDraftService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase72GroupLoanFullBuildFeatureTest extends TestCase
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

    public function test_group_draft_payload_persists_group_data(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-P72-1',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Draft',
            'last_name'       => 'Leader',
            'phone'           => '255712345710',
        ]);
        $product = $this->groupProduct();

        $groupPayload = [
            'name' => 'Test Group',
            'purpose' => 'business',
            'target_member_count' => 5,
            'amount_per_member' => 100000,
            'members' => [],
        ];

        app(LoanApplicationDraftService::class)->save($customer, [
            'phase' => 'application',
            'step' => 1,
            'loan_product_id' => $product->id,
            'form' => ['requested_tenure_months' => 6],
            'group' => $groupPayload,
        ]);

        $draft = app(LoanApplicationDraftService::class)->find($customer, $product->id);
        $this->assertNotNull($draft);
        $this->assertSame('Test Group', $draft->payload['group']['name'] ?? null);

        $resume = app(LoanApplicationDraftService::class)->payloadForWizard($customer, $product->id);
        $this->assertSame('Test Group', $resume['group']['name'] ?? null);
        $this->assertSame(100000, (int) ($resume['group']['amount_per_member'] ?? 0));
    }

    public function test_group_effective_cadence_defaults_weekly(): void
    {
        $this->assertSame('weekly', app(GroupLendingService::class)->effectiveRepaymentCadence($this->groupProduct()));
    }

    public function test_group_member_application_page_shows_invitation_context(): void
    {
        $leader = Customer::create([
            'customer_number' => 'CU-P72-L',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Leader',
            'last_name'       => 'P72',
            'phone'           => '255712345711',
        ]);
        $user = User::factory()->create(['role' => 'borrower']);
        $member = Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-P72-M',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Jane',
            'last_name'       => 'Doe',
            'phone'           => '255712345712',
            'membership_expires_at' => now()->addYear(),
        ]);
        app(PinService::class)->setPin($user, '1234');

        $product = $this->groupProduct();

        $invitation = GroupMemberInvitation::create([
            'leader_customer_id' => $leader->id,
            'loan_product_id'    => $product->id,
            'invitee_first_name' => 'Jane',
            'invitee_last_name'  => 'Doe',
            'invitee_phone'      => '255712345712',
            'token'              => 'test-token-p72-abc-123456789012345678',
            'status'             => 'accepted',
            'customer_id'        => $member->id,
            'draft_reference'    => 'DRF-GL-TEST',
            'amount_per_member'  => 100000,
            'requested_tenure_months' => 6,
            'group_name'         => 'Mwenge Group',
        ]);

        $this->actingAs($user)
            ->withSession(['group_member_invite_token' => $invitation->token])
            ->get(route('site.group-member.application'))
            ->assertOk()
            ->assertSee('DRF-GL-TEST', false)
            ->assertSee('Mwenge Group', false);
    }

    public function test_group_member_limits_default_to_three_minimum(): void
    {
        $limits = app(GroupLendingService::class)->memberLimits();

        $this->assertGreaterThanOrEqual(3, $limits['min']);
    }
}
