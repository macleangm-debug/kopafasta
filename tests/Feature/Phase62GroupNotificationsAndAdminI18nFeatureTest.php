<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\GroupContractSignatureService;
use App\Services\GroupLendingService;
use App\Services\GroupMemberInvitationService;
use App\Services\PinService;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase62GroupNotificationsAndAdminI18nFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NotificationTemplateSeeder::class);
        Mail::fake();
    }

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
            'repayment_cadence'      => 'weekly',
            'application_fee_amount' => 10_000,
        ]);
    }

    /** @return array{application: LoanApplication, leader: Customer, member: Customer, memberRow: LoanGroupMember} */
    protected function contractFixture(): array
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);

        $leader = Customer::create([
            'customer_number'       => 'CU-P62-L',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Leader',
            'last_name'             => 'P62',
            'phone'                 => '255712345960',
            'email'                 => 'leader-p62@example.com',
            'membership_expires_at' => now()->addYear(),
        ]);

        $member = Customer::create([
            'customer_number'       => 'CU-P62-M',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Member',
            'last_name'             => 'P62',
            'phone'                 => '255712345961',
            'email'                 => 'member-p62@example.com',
            'membership_expires_at' => now()->addYear(),
        ]);

        $third = Customer::create([
            'customer_number'       => 'CU-P62-T',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Third',
            'last_name'             => 'P62',
            'phone'                 => '255712345962',
            'email'                 => 'third-p62@example.com',
            'membership_expires_at' => now()->addYear(),
        ]);

        $product = $this->groupProduct();

        $application = LoanApplication::create([
            'customer_id'             => $leader->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P62-001',
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
                ['customer_id' => $third->id, 'requested_amount' => 300_000, 'role' => 'member'],
            ],
            'P62 Group',
            'Business',
        );

        LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id'         => $leader->id,
            'document_type'       => 'loan_contract',
            'reference'           => 'LC-P62-001',
            'status'              => 'signed',
            'signed_at'           => now(),
        ]);

        app(GroupContractSignatureService::class)->syncLeaderFromContract($application->fresh());

        $memberRow = LoanGroupMember::query()
            ->where('loan_group_id', $application->loan_group_id)
            ->where('customer_id', $member->id)
            ->firstOrFail();

        return [
            'application' => $application->fresh(),
            'leader'      => $leader,
            'member'      => $member,
            'memberRow'   => $memberRow,
        ];
    }

    public function test_pending_members_receive_contract_sign_sms_and_in_app(): void
    {
        ['application' => $application, 'member' => $member] = $this->contractFixture();

        app(GroupContractSignatureService::class)->notifyPendingMembers($application);

        $this->assertDatabaseHas('notification_logs', [
            'customer_id' => $member->id,
            'channel'     => 'in_app',
            'template'    => 'group_contract_sign_required',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'customer_id' => $member->id,
            'channel'     => 'sms',
            'template'    => 'group_contract_sign_required',
        ]);
    }

    public function test_internal_member_lookup_sends_consent_sms(): void
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);

        $leader = Customer::create([
            'customer_number'       => 'CU-P62-L2',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Leader',
            'last_name'             => 'Two',
            'phone'                 => '255712345962',
            'membership_expires_at' => now()->addYear(),
        ]);

        $member = Customer::create([
            'customer_number'       => 'CU-P62-M2',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Internal',
            'last_name'             => 'Member',
            'phone'                 => '255712345963',
            'membership_expires_at' => now()->addYear(),
            'member_no'             => 'KPF-TZ-P62MEMB',
        ]);

        $product = $this->groupProduct();

        app(GroupMemberInvitationService::class)->prepareInternalInvitation($leader, $product, $member);

        $this->assertDatabaseHas('notification_logs', [
            'customer_id' => $member->id,
            'channel'     => 'sms',
            'template'    => 'group_member_consent_required',
        ]);
    }

    public function test_group_product_migration_enforces_monthly_cadence(): void
    {
        $product = $this->groupProduct();
        $this->assertSame('weekly', $product->repayment_cadence);

        $migration = require database_path('migrations/2026_06_24_180000_group_products_monthly_cadence.php');
        $migration->up();

        $this->assertSame('monthly', $product->fresh()->repayment_cadence);
    }

    public function test_swahili_admin_group_review_strings_are_available(): void
    {
        $this->assertSame(
            'Ukaguzi wa mkopo wa kikundi',
            __('admin.group_review.title', [], 'sw'),
        );

        $this->assertSame(
            'Mbadala umeombwa',
            __('admin.group_review.underwriting_status.replacement_requested', [], 'sw'),
        );
    }

    public function test_member_contract_sign_notifies_leader_via_sms(): void
    {
        ['application' => $application, 'leader' => $leader, 'member' => $member, 'memberRow' => $memberRow] = $this->contractFixture();

        $user = User::factory()->create(['role' => 'borrower']);
        $member->update(['user_id' => $user->id]);
        app(PinService::class)->setPin($user, '1234');

        $signature = 'data:image/png;base64,'.base64_encode('sig');

        $this->actingAs($user)
            ->post(route('site.borrower.group-contract.sign', $application), [
                'signer_name'    => 'Member P62',
                'signature_data' => $signature,
                'consent'        => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_logs', [
            'customer_id' => $leader->id,
            'channel'     => 'sms',
            'template'    => 'group_contract_member_signed',
        ]);
    }
}
