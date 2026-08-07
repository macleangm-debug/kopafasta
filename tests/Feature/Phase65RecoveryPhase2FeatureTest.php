<?php

namespace Tests\Feature;

use App\Models\ArrearCase;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\PartnerPayment;
use App\Models\PartnerSettlement;
use App\Models\RecoveryAssignment;
use App\Models\RepaymentSchedule;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AuctionHoldService;
use App\Services\PartnerSettlementService;
use App\Services\PinService;
use App\Services\RecoveryCommissionWalletService;
use App\Services\RecoveryPartnerKpiService;
use App\Services\RecoveryPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase65RecoveryPhase2FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function loanFixture(bool $secured = false, string $suffix = ''): array
    {
        $tag = $suffix !== '' ? $suffix : ($secured ? 'S' : 'U');

        $customer = Customer::create([
            'customer_number' => 'CU-P65-'.$tag,
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Borrower',
            'last_name'       => 'Test',
            'phone'           => '255712346'.str_pad((string) abs(crc32($tag) % 1000), 3, '0', STR_PAD_LEFT),
        ]);

        $product = LoanProduct::create([
            'code'                => $secured ? 'AB' : 'IL',
            'name'                => $secured ? 'Asset Backed' : 'Individual Loan',
            'category'            => $secured ? 'asset' : 'individual',
            'interest_rate'       => 0.15,
            'min_amount'          => 100_000,
            'max_amount'          => 5_000_000,
            'tenure_min_months'   => 3,
            'tenure_max_months'   => 12,
            'requires_collateral' => $secured,
            'is_active'           => true,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P65-'.$tag,
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 6,
            'status'                  => 'disbursed',
            'current_stage'           => 'disbursement',
        ]);

        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_application_id' => $application->id,
            'loan_number'         => 'LN-P65-'.$tag,
            'principal_amount'    => 500_000,
            'approved_amount'     => 500_000,
            'outstanding_balance' => 400_000,
            'interest_rate'       => 0.15,
            'tenure_months'       => 6,
            'status'              => 'active',
        ]);

        $arrearCase = ArrearCase::create([
            'loan_id'           => $loan->id,
            'days_past_due'     => 14,
            'amount_in_arrears' => 50_000,
            'penalty_amount'    => 2_500,
            'status'            => 'open',
        ]);

        return compact('customer', 'product', 'application', 'loan', 'arrearCase');
    }

    protected function recoveryPartner(): Vendor
    {
        return Vendor::create([
            'vendor_number' => 'PTR-P65-RC',
            'name'          => 'Call Center Partner',
            'category'      => 'call_center',
            'status'        => 'active',
            'phone'         => '255712346110',
        ]);
    }

    public function test_recovery_policy_matrix_fields(): void
    {
        Setting::setMany([
            'recovery.priority.auctioneer'         => 3,
            'recovery.loan_types.auctioneer'       => 'AB,AL',
            'recovery.collateral_scope.auctioneer' => 'secured',
            'recovery.auto_escalate_type.gps_partner' => false,
        ]);

        $policy = app(RecoveryPolicyService::class);

        $this->assertSame(3, $policy->priorityForType('auctioneer'));
        $this->assertSame('AB,AL', $policy->loanTypesScopeForType('auctioneer'));
        $this->assertSame('secured', $policy->collateralScopeForType('auctioneer'));
        $this->assertFalse($policy->autoEscalateForType('gps_partner'));
    }

    public function test_partner_type_applies_to_loan_by_collateral_scope(): void
    {
        Setting::setMany([
            'recovery.collateral_scope.auctioneer' => 'secured',
        ]);

        $policy = app(RecoveryPolicyService::class);
        $secured = $this->loanFixture(true, 'SEC');
        $unsecuredFixture = $this->loanFixture(false, 'UNSEC');
        $unsecured = $unsecuredFixture['loan'];
        $unsecured->load('product', 'application');

        $this->assertTrue($policy->partnerTypeAppliesToLoan('auctioneer', $secured['loan']));
        $this->assertFalse($policy->partnerTypeAppliesToLoan('auctioneer', $unsecured));
    }

    public function test_recovery_kpi_service_calculates_metrics(): void
    {
        $partner = $this->recoveryPartner();
        $fixture = $this->loanFixture();

        RecoveryAssignment::create([
            'arrear_case_id'       => $fixture['arrearCase']->id,
            'vendor_id'            => $partner->id,
            'partner_type'         => 'call_center',
            'status'               => RecoveryAssignment::STATUS_IN_PROGRESS,
            'original_outstanding' => 400_000,
            'commission_earned'    => 10_000,
            'sla_due_at'           => now()->addDays(3),
            'assigned_at'          => now()->subDays(2),
        ]);

        RecoveryAssignment::create([
            'arrear_case_id'       => $fixture['arrearCase']->id,
            'vendor_id'            => $partner->id,
            'partner_type'         => 'call_center',
            'status'               => RecoveryAssignment::STATUS_COMPLETED,
            'original_outstanding' => 400_000,
            'commission_earned'    => 15_000,
            'commission_paid'      => 0,
            'outcome'              => 'resolved',
            'assigned_at'          => now()->subDays(10),
            'completed_at'         => now()->subDays(3),
        ]);

        $kpi = app(RecoveryPartnerKpiService::class)->kpis($partner);

        $this->assertSame(1, $kpi['assigned_cases']);
        $this->assertSame(1, $kpi['recovered_cases']);
        $this->assertSame(100.0, $kpi['recovery_rate']);
        $this->assertEqualsWithDelta(25_000.0, $kpi['commission_earned'], 0.01);
        $this->assertNotNull($kpi['avg_resolution_days']);
    }

    public function test_commission_wallet_summary_and_dispute(): void
    {
        $partner = $this->recoveryPartner();

        $pending = PartnerPayment::create([
            'vendor_id'      => $partner->id,
            'invoice_number' => 'INV-P65-P',
            'amount'         => 12_000,
            'status'         => 'pending',
            'source_type'    => RecoveryCommissionWalletService::SOURCE_TYPE,
        ]);

        PartnerPayment::create([
            'vendor_id'      => $partner->id,
            'invoice_number' => 'INV-P65-D',
            'amount'         => 8_000,
            'status'         => 'disputed',
            'source_type'    => RecoveryCommissionWalletService::SOURCE_TYPE,
        ]);

        $wallet = app(RecoveryCommissionWalletService::class);
        $summary = $wallet->summary($partner);

        $this->assertSame(12_000, $summary['pending']);
        $this->assertSame(8_000, $summary['disputed']);

        $disputed = $wallet->dispute($pending, $partner, 'Incorrect commission amount');
        $this->assertSame('disputed', $disputed->status);
        $this->assertSame('Incorrect commission amount', $disputed->dispute_reason);
    }

    public function test_settlement_marks_paid_and_syncs_assignment_commission_paid(): void
    {
        $partner = $this->recoveryPartner();
        $fixture = $this->loanFixture();

        $assignment = RecoveryAssignment::create([
            'arrear_case_id'       => $fixture['arrearCase']->id,
            'vendor_id'            => $partner->id,
            'partner_type'         => 'call_center',
            'status'               => RecoveryAssignment::STATUS_COMPLETED,
            'original_outstanding' => 400_000,
            'commission_earned'    => 20_000,
            'commission_paid'      => 0,
            'outcome'              => 'resolved',
            'assigned_at'          => now()->subDays(5),
            'completed_at'         => now(),
        ]);

        $payment = PartnerPayment::create([
            'vendor_id'      => $partner->id,
            'invoice_number' => 'INV-P65-PAID',
            'amount'         => 20_000,
            'status'         => 'approved',
            'source_type'    => RecoveryCommissionWalletService::SOURCE_TYPE,
            'source_id'      => $assignment->id,
        ]);

        $settlement = PartnerSettlement::create([
            'vendor_id'    => $partner->id,
            'reference'    => 'PS-P65-001',
            'period_start' => now()->subWeek()->toDateString(),
            'period_end'   => now()->toDateString(),
            'total_amount' => 20_000,
            'status'       => 'approved',
        ]);

        $payment->update(['partner_settlement_id' => $settlement->id]);

        $admin = User::factory()->create(['role' => 'admin']);
        app(PartnerSettlementService::class)->markSettlementPaid($settlement, $admin, 'bank', 'REF-P65');

        $assignment->refresh();
        $this->assertEqualsWithDelta(20_000.0, (float) $assignment->commission_paid, 0.01);
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_admin_can_save_recovery_sla_matrix(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'admin')->put(route('admin.settings.recovery.save'), [
            'grace_period_days'       => 2,
            'auction_hold_days'       => 4,
            'gps_map_enabled'         => 1,
            'fee_base'                => 'principal',
            'auto_escalate'           => 1,
            'auto_assign_call_center' => 1,
            'call_center_lead_days'   => 1,
            'sla_days_call_center'    => 7,
            'commission_percent_call_center' => 10,
            'markup_percent_call_center'     => 3,
            'fee_type_call_center'           => 'percentage',
            'fixed_amount_call_center'       => '',
            'priority_call_center'             => 1,
            'loan_types_call_center'           => 'IL,GL',
            'collateral_scope_call_center'     => 'all',
            'auto_escalate_type_call_center'   => 1,
            'sla_days_debt_collector'    => 10,
            'commission_percent_debt_collector' => 15,
            'markup_percent_debt_collector'     => 3,
            'fee_type_debt_collector'           => 'percentage',
            'fixed_amount_debt_collector'       => '',
            'priority_debt_collector'             => 2,
            'loan_types_debt_collector'           => 'all',
            'collateral_scope_debt_collector'     => 'all',
            'auto_escalate_type_debt_collector'   => 1,
            'sla_days_auctioneer'    => 11,
            'commission_percent_auctioneer' => 8,
            'markup_percent_auctioneer'     => 2,
            'fee_type_auctioneer'           => 'percentage',
            'fixed_amount_auctioneer'       => '',
            'priority_auctioneer'             => 3,
            'loan_types_auctioneer'           => 'AB',
            'collateral_scope_auctioneer'     => 'secured',
            'auto_escalate_type_auctioneer'   => 1,
            'sla_days_legal_partner'    => 21,
            'commission_percent_legal_partner' => 10,
            'markup_percent_legal_partner'     => 5,
            'fee_type_legal_partner'           => 'percentage',
            'fixed_amount_legal_partner'       => '',
            'priority_legal_partner'             => 4,
            'loan_types_legal_partner'           => 'all',
            'collateral_scope_legal_partner'     => 'all',
            'auto_escalate_type_legal_partner'   => 1,
            'sla_days_gps_partner'    => 5,
            'commission_percent_gps_partner' => 5,
            'markup_percent_gps_partner'     => 2,
            'fee_type_gps_partner'           => 'percentage',
            'fixed_amount_gps_partner'       => '',
            'priority_gps_partner'             => 5,
            'loan_types_gps_partner'           => 'all',
            'collateral_scope_gps_partner'     => 'secured',
            'auto_escalate_type_gps_partner'   => 0,
            'insurance_rate_percent' => 3.5,
            'insurance_has_markup' => 0,
            'insurance_markup_percent' => 0,
            'gps_installer_base_cost' => 100000,
            'gps_installer_monitoring_monthly' => 10000,
            'gps_installer_has_markup' => 1,
            'gps_installer_markup_percent' => 10,
            'valuer_base_cost' => 50000,
            'valuer_has_markup' => 0,
            'valuer_markup_percent' => 0,
        ]);

        $response->assertRedirect();
        $this->assertSame('IL,GL', Setting::get('recovery.loan_types.call_center'));
        $this->assertSame('secured', Setting::get('recovery.collateral_scope.auctioneer'));
        $this->assertFalse((bool) Setting::get('recovery.auto_escalate_type.gps_partner'));
    }

    public function test_recovery_partner_can_view_commission_wallet(): void
    {
        $partner = $this->recoveryPartner();
        $user = User::factory()->create(['role' => 'vendor']);
        app(PinService::class)->setPin($user, '1234');
        $partner->update(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('site.partner.recovery-wallet'))
            ->assertOk()
            ->assertSee('Commission wallet', false)
            ->assertSee('Pending', false);
    }

    public function test_recovery_case_view_shows_should_haves_and_sends_in_app_reminder(): void
    {
        $fixture = $this->loanFixture(false, 'CASE');
        $customer = $fixture['customer'];
        $customer->update([
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'ward' => 'Kariakoo',
            'street' => 'Uhuru St 12',
        ]);

        RepaymentSchedule::create([
            'loan_id' => $fixture['loan']->id,
            'installment_no' => 1,
            'due_date' => now()->addDays(3)->toDateString(),
            'principal_due' => 80_000,
            'interest_due' => 10_000,
            'total_due' => 90_000,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        $partner = $this->recoveryPartner();
        $user = User::factory()->create(['role' => 'vendor']);
        app(PinService::class)->setPin($user, '1234');
        $partner->update(['user_id' => $user->id]);

        $assignment = RecoveryAssignment::create([
            'arrear_case_id'         => $fixture['arrearCase']->id,
            'vendor_id'              => $partner->id,
            'partner_type'           => 'call_center',
            'status'                 => RecoveryAssignment::STATUS_IN_PROGRESS,
            'original_outstanding'   => 400_000,
            'commission_percent'     => 5,
            'commission_earned'      => 20_000,
            'sla_due_at'             => now()->addDay(),
            'assigned_at'            => now()->subDay(),
        ]);

        Setting::set('messaging.enabled', true);
        Setting::set('messaging.channels', [
            'sms' => false,
            'email' => false,
            'in_app' => true,
            'whatsapp' => false,
            'push' => false,
        ]);

        $this->actingAs($user)
            ->get(route('site.partner.recovery-case', $assignment))
            ->assertOk()
            ->assertSee('Upcoming installments', false)
            ->assertSee('Suggested talk track', false)
            ->assertSee('Send in-app reminder', false)
            ->assertSee('Uhuru St 12', false)
            ->assertSee('Open commission wallet', false)
            ->assertSee('Activity on this assignment', false);

        $this->actingAs($user)
            ->post(route('site.partner.recovery-case.remind', $assignment))
            ->assertRedirect();

        $this->assertDatabaseHas('collection_actions', [
            'arrear_case_id' => $fixture['arrearCase']->id,
            'recovery_assignment_id' => $assignment->id,
            'action_type' => 'reminder_sent',
        ]);
    }

    public function test_repossession_starts_auction_hold_and_borrower_sees_countdown(): void
    {
        Setting::set('recovery.auction_hold_days', 4);
        Setting::set('messaging.enabled', true);
        Setting::set('messaging.channels', [
            'sms' => false,
            'email' => false,
            'in_app' => true,
            'whatsapp' => false,
            'push' => false,
        ]);

        $fixture = $this->loanFixture(true, 'REPO');
        $partner = Vendor::create([
            'vendor_number' => 'PTR-P65-DC',
            'name'          => 'Debt Collector Co',
            'category'      => 'debt_collector',
            'status'        => 'active',
            'phone'         => '255712346120',
        ]);
        $user = User::factory()->create(['role' => 'vendor']);
        app(PinService::class)->setPin($user, '1234');
        $partner->update(['user_id' => $user->id]);

        $task = \App\Models\PartnerTask::create([
            'partner_id' => $partner->id,
            'loan_id' => $fixture['loan']->id,
            'loan_application_id' => $fixture['application']->id,
            'task_type' => 'field_visit',
            'status' => 'in_progress',
            'customer_name' => 'Borrower',
        ]);

        $assignment = RecoveryAssignment::create([
            'arrear_case_id'       => $fixture['arrearCase']->id,
            'vendor_id'            => $partner->id,
            'partner_type'         => 'debt_collector',
            'status'               => RecoveryAssignment::STATUS_IN_PROGRESS,
            'original_outstanding' => 400_000,
            'commission_percent'   => 10,
            'commission_earned'    => 40_000,
            'sla_due_at'           => now()->addDays(5),
            'assigned_at'          => now()->subDay(),
            'vendor_task_id'       => $task->id,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('repo.jpg');

        $this->actingAs($user)
            ->post(route('site.partner.recovery-case.action', $assignment), [
                'action' => 'repossession_complete',
                'notes' => 'Asset secured at yard',
                'file' => $file,
            ])
            ->assertRedirect();

        $case = $fixture['arrearCase']->fresh();
        $this->assertNotNull($case->repossessed_at);
        $this->assertSame(AuctionHoldService::STATUS_PENDING, $case->auction_status);
        $this->assertTrue($case->auction_eligible_at->isSameDay(now()->addDays(4)));

        $status = app(AuctionHoldService::class)->statusForLoan($fixture['loan']->fresh());
        $this->assertTrue($status['repossessed']);
        $this->assertSame(4, $status['days_until_auction']);

        $borrowerUser = User::factory()->create(['role' => 'borrower']);
        $fixture['customer']->update(['user_id' => $borrowerUser->id]);
        app(PinService::class)->setPin($borrowerUser, '1234');

        $this->actingAs($borrowerUser)
            ->get(route('site.borrower.loans.show', $fixture['loan']))
            ->assertOk()
            ->assertSee('repossessed', false)
            ->assertSee('day', false);
    }

    public function test_gps_install_persists_tracking_url_for_collateral_map(): void
    {
        $fixture = $this->loanFixture(true, 'GPS');
        $asset = \App\Models\CustomerAsset::create([
            'customer_id' => $fixture['customer']->id,
            'asset_type' => 'vehicle',
            'label' => 'Bajaj Boxer',
            'registration_number' => 'T123ABC',
            'is_active' => true,
            'metadata' => [],
        ]);
        \App\Models\LoanApplicationAsset::create([
            'loan_application_id' => $fixture['application']->id,
            'customer_asset_id' => $asset->id,
            'asset_type' => 'vehicle',
            'gps_required' => true,
            'is_primary' => true,
            'uw_status' => 'accepted',
        ]);

        $installer = Vendor::create([
            'vendor_number' => 'PTR-P65-GPS',
            'name' => 'GPS Install Co',
            'category' => 'gps_installer',
            'status' => 'active',
            'phone' => '255712346130',
        ]);
        $user = User::factory()->create(['role' => 'vendor']);
        app(PinService::class)->setPin($user, '1234');
        $installer->update(['user_id' => $user->id]);

        $task = \App\Models\PartnerTask::create([
            'partner_id' => $installer->id,
            'loan_application_id' => $fixture['application']->id,
            'task_type' => 'gps_install',
            'status' => 'in_progress',
            'customer_name' => 'Borrower',
        ]);

        $this->actingAs($user)
            ->post(route('site.partner.task.complete', $task), [
                'gps_serial' => 'SN-998877',
                'gps_provider' => 'generic',
                'gps_device_id' => 'IMEI-55',
                'gps_tracking_url' => 'https://track.example.com/device/IMEI-55',
                'notes' => 'Installed under dash',
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame('SN-998877', $task->gps_serial);
        $this->assertSame('https://track.example.com/device/IMEI-55', $task->gps_tracking_url);

        Setting::set('gps.map_enabled', false);
        $itemsOff = app(\App\Services\GpsDeviceService::class)->collateralForLoan($fixture['loan']->fresh());
        $this->assertFalse($itemsOff[0]['can_view_asset']);

        Setting::set('gps.map_enabled', true);
        $items = app(\App\Services\GpsDeviceService::class)->collateralForLoan($fixture['loan']->fresh());
        $this->assertNotEmpty($items);
        $this->assertSame('https://track.example.com/device/IMEI-55', $items[0]['tracking_url']);
        $this->assertTrue($items[0]['can_view_asset']);
        $this->assertSame('secured', $items[0]['gps_status']);

        $contact = app(\App\Services\GpsDeviceService::class)->installerContactForLoan($fixture['loan']->fresh());
        $this->assertSame('GPS Install Co', $contact['name']);
        $this->assertSame('255712346130', $contact['phone']);
    }

    public function test_auction_hold_keeps_same_partner_when_collector_also_auctions(): void
    {
        Setting::set('recovery.auction_hold_days', 1);

        $fixture = $this->loanFixture(true, 'CONT');
        $partner = Vendor::create([
            'vendor_number' => 'PTR-P65-BOTH',
            'name' => 'Repo And Auction Co',
            'category' => 'debt_collector',
            'roles' => ['debt_collector', 'auctioneer'],
            'status' => 'active',
            'phone' => '255712346140',
        ]);
        $otherAuctioneer = Vendor::create([
            'vendor_number' => 'PTR-P65-AUC',
            'name' => 'Other Auction House',
            'category' => 'auctioneer',
            'roles' => ['auctioneer'],
            'status' => 'active',
            'phone' => '255712346141',
        ]);

        RecoveryAssignment::create([
            'arrear_case_id' => $fixture['arrearCase']->id,
            'vendor_id' => $partner->id,
            'partner_type' => 'debt_collector',
            'status' => RecoveryAssignment::STATUS_COMPLETED,
            'original_outstanding' => 400_000,
            'commission_percent' => 10,
            'commission_earned' => 40_000,
            'sla_due_at' => now()->subDay(),
            'assigned_at' => now()->subDays(3),
            'completed_at' => now()->subDay(),
            'outcome' => 'repossessed',
        ]);

        $fixture['arrearCase']->update([
            'repossessed_at' => now()->subDays(2),
            'auction_eligible_at' => now()->subHour(),
            'auction_status' => AuctionHoldService::STATUS_PENDING,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $assignment = app(AuctionHoldService::class)->assignAuctioneer($fixture['arrearCase']->fresh(), $admin);

        $this->assertNotNull($assignment);
        $this->assertSame($partner->id, $assignment->partner_id);
        $this->assertSame('auctioneer', $assignment->partner_type);
        $this->assertNotSame($otherAuctioneer->id, $assignment->partner_id);
    }
}
