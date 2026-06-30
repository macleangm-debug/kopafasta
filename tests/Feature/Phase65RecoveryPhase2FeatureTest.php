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
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
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
        ]);

        $response->assertRedirect();
        $this->assertSame('IL,GL', Setting::get('recovery.loan_types.call_center'));
        $this->assertSame('secured', Setting::get('recovery.collateral_scope.auctioneer'));
        $this->assertFalse((bool) Setting::get('recovery.auto_escalate_type.gps_partner'));
    }

    public function test_recovery_partner_can_view_commission_wallet(): void
    {
        $partner = $this->recoveryPartner();
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $partner->update(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('site.partner.recovery-wallet'))
            ->assertOk()
            ->assertSee('Commission wallet', false)
            ->assertSee('Pending', false);
    }
}
