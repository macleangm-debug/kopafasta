<?php

namespace Tests\Feature;

use App\Models\CreditHistory;
use App\Models\Customer;
use App\Models\GroupMemberInvitation;
use App\Models\LoanApplication;
use App\Models\LoanGroup;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\GroupApplicationStatusService;
use App\Services\GroupApplyService;
use App\Services\GroupLendingService;
use App\Services\GroupMemberProgressService;
use App\Services\GroupScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase64GroupApplicationStatusFeatureTest extends TestCase
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

    protected function verifiedCustomer(string $suffix, string $phone): Customer
    {
        return Customer::create([
            'customer_number'       => 'CU-P64-'.$suffix,
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Member',
            'last_name'             => $suffix,
            'phone'                 => $phone,
            'membership_expires_at' => now()->addYear(),
            'onboarded_at'          => now(),
            'nida_verification_status' => 'verified',
            'face_verification_status' => 'verified',
            'monthly_income'        => 1_500_000,
        ]);
    }

    public function test_draft_status_when_group_setup_incomplete(): void
    {
        $status = app(GroupApplicationStatusService::class)->resolveFromDraftPayload([
            'name'                => '',
            'purpose'             => 'business',
            'target_member_count' => 5,
            'members'             => [],
        ]);

        $this->assertSame('draft', $status['key']);
    }

    public function test_inviting_members_when_member_slots_unfilled(): void
    {
        $leader = $this->verifiedCustomer('L1', '255712346001');

        $status = app(GroupApplicationStatusService::class)->resolveFromDraftPayload([
            'name'                => 'Mwenge Group',
            'purpose'             => 'business',
            'target_member_count' => 3,
            'members'             => [
                app(GroupApplyService::class)->leaderMemberRow($leader, 300_000),
            ],
        ]);

        $this->assertSame('inviting_members', $status['key']);
    }

    public function test_member_completion_when_members_added_but_kyc_incomplete(): void
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);

        $leader = $this->verifiedCustomer('L2', '255712346002');
        $member = Customer::create([
            'customer_number' => 'CU-P64-M2',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Incomplete',
            'last_name'       => 'Member',
            'phone'           => '255712346003',
        ]);

        $status = app(GroupApplicationStatusService::class)->resolveFromDraftPayload([
            'name'                => 'Test Group',
            'purpose'             => 'business',
            'target_member_count' => 2,
            'members'             => [
                ['customer_id' => $leader->id, 'role' => 'leader', 'requested_amount' => 300_000],
                ['customer_id' => $member->id, 'role' => 'member', 'requested_amount' => 300_000],
            ],
        ]);

        $this->assertSame('member_completion', $status['key']);
    }

    public function test_ready_for_submission_when_all_members_verified(): void
    {
        $this->mock(GroupMemberProgressService::class, function ($mock): void {
            $mock->shouldReceive('summarize')->once()->andReturn([
                'target'              => 2,
                'added'               => 2,
                'verified'            => 2,
                'profiles_complete'   => 2,
                'invitations_pending' => 0,
                'pending'             => 0,
                'members'             => [
                    ['status_key' => 'kyc_complete'],
                    ['status_key' => 'kyc_complete'],
                ],
                'can_submit'          => true,
                'summary'             => [],
            ]);
        });

        $status = app(GroupApplicationStatusService::class)->resolveFromDraftPayload([
            'name'                => 'Ready Group',
            'purpose'             => 'business',
            'target_member_count' => 2,
            'members'             => [
                ['customer_id' => 1, 'role' => 'leader'],
                ['customer_id' => 2, 'role' => 'member'],
            ],
        ]);

        $this->assertSame('ready_for_submission', $status['key']);
    }

    public function test_application_status_maps_workflow_stages(): void
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);

        $leader = $this->verifiedCustomer('L4', '255712346006');
        $member = $this->verifiedCustomer('M4', '255712346007');
        $third = $this->verifiedCustomer('T4', '255712346016');
        $product = $this->groupProduct();

        $application = LoanApplication::create([
            'customer_id'             => $leader->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P64-001',
            'status'                  => 'submitted',
            'current_stage'           => 'screening',
            'requested_amount'        => 600_000,
            'requested_tenure_months' => 6,
            'submitted_at'            => now(),
        ]);

        app(GroupLendingService::class)->createForApplication(
            $application,
            [
                ['customer_id' => $leader->id, 'requested_amount' => 300_000, 'role' => 'leader'],
                ['customer_id' => $member->id, 'requested_amount' => 300_000, 'role' => 'member'],
                ['customer_id' => $third->id, 'requested_amount' => 300_000, 'role' => 'member'],
            ],
            'Workflow Group',
            'Business',
            3,
        );

        $statusService = app(GroupApplicationStatusService::class);

        $this->assertSame('under_review', $statusService->resolveFromApplication($application->fresh())['key']);

        $application->update(['current_stage' => 'approval', 'status' => 'approved', 'approved_at' => now()]);
        $this->assertSame('approved', $statusService->resolveFromApplication($application->fresh())['key']);

        $application->update(['current_stage' => 'rejected', 'status' => 'rejected']);
        $this->assertSame('rejected', $statusService->resolveFromApplication($application->fresh())['key']);
    }

    public function test_sync_application_persists_status_and_scoring_on_loan_group(): void
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);

        $leader = $this->verifiedCustomer('L5', '255712346008');
        $member = $this->verifiedCustomer('M5', '255712346009');
        $third = $this->verifiedCustomer('T5', '255712346017');
        $product = $this->groupProduct();

        CreditHistory::create([
            'customer_id' => $leader->id,
            'source'      => 'crb_stub',
            'score'       => 720,
            'checked_at'  => now(),
        ]);
        CreditHistory::create([
            'customer_id' => $member->id,
            'source'      => 'crb_stub',
            'score'       => 680,
            'checked_at'  => now(),
        ]);
        CreditHistory::create([
            'customer_id' => $third->id,
            'source'      => 'crb_stub',
            'score'       => 700,
            'checked_at'  => now(),
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $leader->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P64-002',
            'status'                  => 'submitted',
            'current_stage'           => 'submitted',
            'requested_amount'        => 600_000,
            'requested_tenure_months' => 6,
            'submitted_at'            => now(),
            'credit_appraisal_payload' => [
                'group_member_crb' => [
                    ['customer_id' => $leader->id, 'score' => 720],
                    ['customer_id' => $member->id, 'score' => 680],
                    ['customer_id' => $third->id, 'score' => 700],
                ],
            ],
        ]);

        app(GroupLendingService::class)->createForApplication(
            $application,
            [
                ['customer_id' => $leader->id, 'requested_amount' => 300_000, 'role' => 'leader'],
                ['customer_id' => $member->id, 'requested_amount' => 300_000, 'role' => 'member'],
                ['customer_id' => $third->id, 'requested_amount' => 300_000, 'role' => 'member'],
            ],
            'Scored Group',
            'Business',
            3,
        );

        $scoring = app(GroupScoringService::class)->score(
            [
                ['customer_id' => $leader->id, 'requested_amount' => 300_000],
                ['customer_id' => $member->id, 'requested_amount' => 300_000],
            ],
            2,
            $application,
        );

        app(GroupApplicationStatusService::class)->syncApplication($application->fresh(['loanGroup']), $scoring);

        $group = LoanGroup::query()->where('primary_application_id', $application->id)->firstOrFail();
        $this->assertSame('under_review', $group->application_status);
        $this->assertIsArray($group->scoring_snapshot);
        $this->assertEqualsWithDelta(700.0, $group->scoring_snapshot['average_credit_score'], 0.01);
        $this->assertGreaterThan(0, $group->scoring_snapshot['group_risk_score']);
    }

    public function test_group_scoring_service_computes_credit_and_income_averages(): void
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);

        $leader = $this->verifiedCustomer('L6', '255712346010');
        $member = $this->verifiedCustomer('M6', '255712346011');

        CreditHistory::create([
            'customer_id' => $leader->id,
            'source'      => 'crb_stub',
            'score'       => 750,
            'checked_at'  => now(),
        ]);
        CreditHistory::create([
            'customer_id' => $member->id,
            'source'      => 'crb_stub',
            'score'       => 700,
            'checked_at'  => now(),
        ]);

        $scoring = app(GroupScoringService::class)->scoreFromDraftPayload([
            'target_member_count' => 2,
            'members'             => [
                ['customer_id' => $leader->id, 'requested_amount' => 300_000],
                ['customer_id' => $member->id, 'requested_amount' => 300_000],
            ],
        ]);

        $this->assertEqualsWithDelta(725.0, $scoring['average_credit_score'], 0.01);
        $this->assertEqualsWithDelta(1_500_000.0, $scoring['average_income'], 0.01);
        $this->assertContains($scoring['risk_band'], ['low', 'medium', 'high']);
        $this->assertGreaterThanOrEqual(0, $scoring['group_risk_score']);
        $this->assertLessThanOrEqual(100, $scoring['group_risk_score']);
    }

    public function test_refresh_group_member_statuses_endpoint_returns_status_and_scoring(): void
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);

        $leader = $this->verifiedCustomer('L7', '255712346012');
        $user = User::factory()->create([
            'role'     => 'borrower',
            'pin_hash' => bcrypt('1234'),
        ]);
        $leader->update(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson(route('site.borrower.apply.group-member-statuses'), [
            'members' => [
                ['customer_id' => $leader->id, 'role' => 'leader', 'requested_amount' => 300_000],
            ],
            'target_member_count' => 2,
            'group'               => [
                'name'                => 'API Group',
                'purpose'             => 'business',
                'target_member_count' => 2,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('application_status.key', 'inviting_members')
            ->assertJsonStructure([
                'ok',
                'summary',
                'application_status' => ['key', 'label', 'tone'],
                'scoring'            => [
                    'member_completion_percent',
                    'average_credit_score',
                    'average_income',
                    'group_risk_score',
                    'risk_band',
                ],
            ]);
    }
}
