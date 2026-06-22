<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\ApplicationDisbursementReadinessService;
use App\Services\GroupContractSignatureService;
use App\Services\GroupLendingService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase58GroupContractSignaturesFeatureTest extends TestCase
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

    /** @return array{application: LoanApplication, leader: Customer, member: Customer, memberRow: LoanGroupMember} */
    protected function groupApplicationFixture(): array
    {
        Setting::setMany(['loan.group_min_members' => 2, 'loan.group_max_members' => 10]);

        $leader = Customer::create([
            'customer_number' => 'CU-P58-L',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Leader',
            'last_name'       => 'P58',
            'phone'           => '255712345920',
        ]);

        $member = Customer::create([
            'customer_number' => 'CU-P58-M',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Member',
            'last_name'       => 'P58',
            'phone'           => '255712345921',
        ]);

        $product = $this->groupProduct();

        $application = LoanApplication::create([
            'customer_id'             => $leader->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P58-001',
            'status'                  => 'approved',
            'current_stage'           => 'disbursement',
            'offer_status'            => 'accepted',
            'requested_amount'        => 600_000,
            'requested_tenure_months' => 6,
        ]);

        $group = app(GroupLendingService::class)->createForApplication(
            $application,
            [
                ['customer_id' => $leader->id, 'requested_amount' => 300_000, 'role' => 'leader'],
                ['customer_id' => $member->id, 'requested_amount' => 300_000, 'role' => 'member'],
            ],
            'P58 Group',
            'Business',
        );

        $memberRow = LoanGroupMember::query()
            ->where('loan_group_id', $group->id)
            ->where('customer_id', $member->id)
            ->firstOrFail();

        LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id'         => $leader->id,
            'document_type'       => 'loan_contract',
            'reference'           => 'LC-P58-001',
            'status'              => 'signed',
            'signed_at'           => now(),
        ]);

        app(GroupContractSignatureService::class)->syncLeaderFromContract($application->fresh());

        return compact('application', 'leader', 'member', 'memberRow');
    }

    public function test_group_contract_progress_tracks_member_signatures(): void
    {
        ['application' => $application, 'member' => $member, 'memberRow' => $memberRow] = $this->groupApplicationFixture();

        $service = app(GroupContractSignatureService::class);
        $progress = $service->progress($application->fresh());

        $this->assertNotNull($progress);
        $this->assertSame(2, $progress['target']);
        $this->assertSame(1, $progress['signed']);
        $this->assertSame(1, $progress['pending']);
        $this->assertFalse($progress['all_signed']);

        $service->recordSignature(
            $memberRow,
            $member,
            'Member P58',
            'data:image/png;base64,iVBORw0KGgo=',
        );

        $progress = $service->progress($application->fresh());
        $this->assertTrue($progress['all_signed']);
        $this->assertTrue(app(ApplicationDisbursementReadinessService::class)->contractSigned($application->fresh()));
    }

    public function test_group_member_can_sign_contract_via_portal(): void
    {
        ['application' => $application, 'member' => $member] = $this->groupApplicationFixture();

        $user = User::factory()->create(['role' => 'borrower']);
        $member->update(['user_id' => $user->id]);
        app(PinService::class)->setPin($user, '1234');

        $this->actingAs($user)
            ->post(route('site.borrower.group-contract.sign', $application), [
                'signer_name'    => 'Member P58',
                'signature_data' => 'data:image/png;base64,iVBORw0KGgo=',
                'consent'        => '1',
            ])
            ->assertRedirect(route('site.borrower.group-contract.show', $application));

        $this->assertDatabaseHas('loan_group_members', [
            'loan_group_id'             => $application->loan_group_id,
            'customer_id'               => $member->id,
            'contract_signature_status' => 'signed',
        ]);
    }

    public function test_disbursement_blocked_until_all_group_members_sign(): void
    {
        ['application' => $application] = $this->groupApplicationFixture();

        $readiness = app(ApplicationDisbursementReadinessService::class);

        $this->assertFalse($readiness->contractSigned($application));
        $this->assertContains(
            'All group members must sign the loan contract before disbursement.',
            $readiness->blockingMessages($application),
        );
    }
}
