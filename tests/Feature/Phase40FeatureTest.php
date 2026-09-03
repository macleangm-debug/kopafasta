<?php

namespace Tests\Feature;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\Repayment;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\AssetLendingRepaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase40FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_loan_repayment_accrues_supplier_principal_payout(): void
    {
        User::factory()->create(['role' => 'admin']);

        $vendor = Vendor::create([
            'vendor_number'  => 'PTR-P40-001',
            'name'           => 'Managed Supplier',
            'category'       => 'supplier',
            'status'         => 'active',
            'supplier_type'  => 'managed_loan',
            'regions'        => ['Dar es Salaam'],
        ]);

        $asset = MarketplaceAsset::create([
            'vendor_id'          => $vendor->id,
            'slug'               => 'p40-asset',
            'title'              => 'Fleet Truck',
            'category'           => 'vehicle',
            'supplier_name'      => $vendor->name,
            'asset_value'        => 5_000_000,
            'supplier_deposit'   => 1_000_000,
            'customer_deposit'   => 1_100_000,
            'weekly_installment' => 120_000,
            'max_tenure_months'  => 24,
            'is_active'          => true,
        ]);

        $product = LoanProduct::create([
            'code'              => 'AL',
            'name'              => 'Asset Lending',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 500_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-P40-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Repay',
            'last_name'       => 'Borrower',
            'phone'           => '255712345840',
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P40-001',
            'status'                  => 'approved',
            'current_stage'           => 'disbursement',
            'requested_amount'        => 3_000_000,
            'requested_tenure_months' => 12,
        ]);

        AssetReservation::create([
            'customer_id'          => $customer->id,
            'loan_application_id'  => $application->id,
            'marketplace_asset_id' => $asset->id,
            'status'               => 'deposit_paid',
            'deposit_amount'       => 1_100_000,
            'deposit_status'       => 'paid',
        ]);

        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_application_id' => $application->id,
            'loan_number'         => 'LN-P40-001',
            'principal_amount'    => 3_000_000,
            'approved_amount'     => 3_000_000,
            'interest_rate'       => 0.15,
            'tenure_months'       => 12,
            'outstanding_balance' => 2_500_000,
            'status'              => 'active',
        ]);

        $repayment = Repayment::create([
            'loan_id'             => $loan->id,
            'reference'           => 'RCP-P40-001',
            'channel'             => 'mobile_money',
            'amount'              => 150_000,
            'status'              => 'received',
            'principal_component' => 100_000,
            'interest_component'  => 50_000,
            'paid_at'             => now(),
        ]);

        $payment = app(AssetLendingRepaymentService::class)->accruePrincipalPayout($loan, $repayment);

        $this->assertInstanceOf(VendorPayment::class, $payment);
        $this->assertSame($vendor->id, $payment->vendor_id);
        $this->assertSame('managed_loan_repayment', $payment->source_type);
        $this->assertSame($repayment->id, $payment->source_id);
        $this->assertSame(100_000, $payment->amount);

        $duplicate = app(AssetLendingRepaymentService::class)->accruePrincipalPayout($loan, $repayment);
        $this->assertNull($duplicate);
    }

    public function test_upfront_settlement_supplier_does_not_accrue_repayment_payout(): void
    {
        $vendor = Vendor::create([
            'vendor_number' => 'PTR-P40-002',
            'name'          => 'Upfront Supplier',
            'category'      => 'supplier',
            'status'        => 'active',
            'supplier_type' => 'upfront_settlement',
            'regions'       => ['Mwanza'],
        ]);

        $asset = MarketplaceAsset::create([
            'vendor_id'          => $vendor->id,
            'slug'               => 'p40-upfront',
            'title'              => 'Upfront Asset',
            'category'           => 'vehicle',
            'supplier_name'      => $vendor->name,
            'asset_value'        => 2_000_000,
            'supplier_deposit'   => 400_000,
            'customer_deposit'   => 440_000,
            'weekly_installment' => 50_000,
            'max_tenure_months'  => 12,
            'is_active'          => true,
        ]);

        $product = LoanProduct::create([
            'code'              => 'AL',
            'name'              => 'Asset Lending',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 500_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-P40-002',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Upfront',
            'last_name'       => 'Borrower',
            'phone'           => '255712345841',
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P40-002',
            'status'                  => 'approved',
            'current_stage'           => 'disbursement',
            'requested_amount'        => 1_500_000,
            'requested_tenure_months' => 12,
        ]);

        AssetReservation::create([
            'customer_id'          => $customer->id,
            'loan_application_id'  => $application->id,
            'marketplace_asset_id' => $asset->id,
            'status'               => 'deposit_paid',
            'deposit_amount'       => 440_000,
            'deposit_status'       => 'paid',
        ]);

        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_application_id' => $application->id,
            'loan_number'         => 'LN-P40-002',
            'principal_amount'    => 1_500_000,
            'approved_amount'     => 1_500_000,
            'interest_rate'       => 0.15,
            'tenure_months'       => 12,
            'outstanding_balance' => 1_200_000,
            'status'              => 'active',
        ]);

        $repayment = Repayment::create([
            'loan_id'             => $loan->id,
            'reference'           => 'RCP-P40-002',
            'channel'             => 'cash',
            'amount'              => 80_000,
            'status'              => 'received',
            'principal_component' => 60_000,
            'interest_component'  => 20_000,
            'paid_at'             => now(),
        ]);

        $payment = app(AssetLendingRepaymentService::class)->accruePrincipalPayout($loan, $repayment);

        $this->assertNull($payment);
        $this->assertDatabaseCount('partner_payments', 0);
    }

    public function test_loan_rules_save_group_member_limits(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.loan-rules.save'), [
                'default_grace_days'   => 7,
                'default_penalty_rate' => 1,
                'penalty_basis'        => 'per_day',
                'penalty_cap_percent'  => 30,
                'max_tenure_months'    => 24,
                'min_tenure_months'    => 1,
                'max_loan_amount'      => 50_000_000,
                'min_loan_amount'      => 50_000,
                'min_guarantors'       => 1,
                'max_restructures'     => 2,
                'restructure_cooldown_days' => 30,
                'group_min_members'    => 5,
                'group_max_members'    => 10,
                'group_repayment_cadence' => 'weekly',
                'group_leader_unlock_repayments' => 2,
                'group_payout_order' => 'leader_first',
                'group_application_fee_per_member' => 1,
                'group_post_approval_fee_per_group' => 1,
            ])
            ->assertRedirect();

        $values = Setting::group('loan');

        $this->assertSame('5', (string) ($values['group_min_members'] ?? ''));
        $this->assertSame('10', (string) ($values['group_max_members'] ?? ''));
    }

    public function test_field_partner_requires_operating_regions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name'     => 'No Region Valuer',
                'category' => 'valuer',
                'status'   => 'active',
                'phone'    => '255712345842',
            ])
            ->assertSessionHasErrors('regions');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name'     => 'Regional Valuer',
                'category' => 'valuer',
                'status'   => 'active',
                'phone'    => '255712345843',
                'regions'  => ['Arusha'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('partners', [
            'name' => 'Regional Valuer',
        ]);
    }

    public function test_affiliate_partner_does_not_require_regions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name'     => 'Nationwide Affiliate',
                'category' => 'affiliate',
                'status'   => 'active',
                'phone'    => '255712345844',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('partners', [
            'name'     => 'Nationwide Affiliate',
            'category' => 'affiliate',
            'coverage_type' => 'nationwide',
        ]);
    }
}
