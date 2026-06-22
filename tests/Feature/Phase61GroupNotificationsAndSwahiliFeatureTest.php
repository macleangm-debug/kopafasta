<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\GroupLendingService;
use App\Services\GroupLoanMemberReviewService;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase61GroupNotificationsAndSwahiliFeatureTest extends TestCase
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
            'repayment_cadence'      => 'monthly',
            'application_fee_amount' => 10_000,
        ]);
    }

    /** @return array{application: LoanApplication, leader: Customer, member: LoanGroupMember} */
    protected function groupFixture(): array
    {
        Setting::setMany(['loan.group_min_members' => 2, 'loan.group_max_members' => 10]);

        $leader = Customer::create([
            'customer_number'       => 'CU-P61-L',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Leader',
            'last_name'             => 'P61',
            'phone'                 => '255712345950',
            'email'                 => 'leader-p61@example.com',
            'membership_expires_at' => now()->addYear(),
        ]);

        $member = Customer::create([
            'customer_number'       => 'CU-P61-M',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Member',
            'last_name'             => 'P61',
            'phone'                 => '255712345951',
            'membership_expires_at' => now()->addYear(),
        ]);

        $product = $this->groupProduct();

        $application = LoanApplication::create([
            'customer_id'             => $leader->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P61-001',
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
            'P61 Group',
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

    public function test_admin_replacement_request_notifies_leader_via_sms_and_in_app(): void
    {
        ['application' => $application, 'member' => $memberRow, 'leader' => $leader] = $this->groupFixture();

        $officer = User::factory()->create(['role' => 'officer']);

        $this->actingAs($officer);
        $this->actingAs($officer, 'admin')
            ->post(route('admin.loan-applications.request-group-member-replacement', [$application, $memberRow]), [
                'reason' => 'CRB score below threshold.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_logs', [
            'customer_id' => $leader->id,
            'channel'     => 'in_app',
            'template'    => 'group_member_replacement_requested',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'customer_id' => $leader->id,
            'channel'     => 'sms',
            'template'    => 'group_member_replacement_requested',
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            NotificationLog::query()
                ->where('customer_id', $leader->id)
                ->where('channel', 'email')
                ->where('template', 'group_member_replacement_requested')
                ->count(),
        );
    }

    public function test_request_replacement_service_sends_multi_channel_notification(): void
    {
        ['application' => $application, 'member' => $memberRow, 'leader' => $leader] = $this->groupFixture();

        app(GroupLoanMemberReviewService::class)->requestReplacement(
            $memberRow,
            null,
            'Please add a new member.',
        );

        $this->assertDatabaseHas('loan_group_members', [
            'id'                  => $memberRow->id,
            'underwriting_status' => 'replacement_requested',
        ]);

        $this->assertTrue(
            NotificationLog::query()
                ->where('customer_id', $leader->id)
                ->where('template', 'group_member_replacement_requested')
                ->whereIn('channel', ['in_app', 'sms'])
                ->exists(),
        );
    }

    public function test_swahili_group_contract_strings_are_available(): void
    {
        $this->assertSame(
            'Saini za mkataba wa kikundi',
            __('borrower.apply.group.contract_dashboard_title', [], 'sw'),
        );

        $this->assertSame(
            'Mbadala wa mwanachama unahitajika',
            __('borrower.apply.group.replacement_requested_title', [], 'sw'),
        );

        $this->assertSame(
            'Husasishwa kiotomatiki',
            __('borrower.apply.group.contract_auto_refresh', [], 'sw'),
        );
    }
}
