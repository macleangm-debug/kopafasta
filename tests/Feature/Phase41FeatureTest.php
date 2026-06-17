<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\Repayment;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AssetLendingRepaymentGlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase41FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_loan_repayment_posts_supplier_payable_journal(): void
    {
        User::factory()->create(['role' => 'admin']);

        $cash = ChartOfAccount::create(['code' => '1010', 'name' => 'Bank', 'type' => 'asset', 'is_active' => true]);
        $receivable = ChartOfAccount::create(['code' => '1100', 'name' => 'Loans Receivable', 'type' => 'asset', 'is_active' => true]);
        $supplierPayable = ChartOfAccount::create(['code' => '2130', 'name' => 'Asset Supplier Payable', 'type' => 'liability', 'is_active' => true]);

        Setting::setMany([
            'finance.cash_gl_account_id' => $cash->id,
            'finance.loan_receivable_gl_account_id' => $receivable->id,
            'finance.supplier_payable_gl_account_id' => $supplierPayable->id,
            'finance.asset_lending_principal_clearing_gl_account_id' => $receivable->id,
        ]);

        $vendor = Vendor::create([
            'vendor_number' => 'PTR-P41-001',
            'name'          => 'GL Supplier',
            'category'      => 'supplier',
            'status'        => 'active',
            'supplier_type' => 'managed_loan',
            'regions'       => ['Dar es Salaam'],
        ]);

        $asset = MarketplaceAsset::create([
            'vendor_id'          => $vendor->id,
            'slug'               => 'p41-asset',
            'title'              => 'Truck',
            'category'           => 'vehicle',
            'supplier_name'      => $vendor->name,
            'asset_value'        => 4_000_000,
            'supplier_deposit'   => 800_000,
            'customer_deposit'   => 880_000,
            'weekly_installment' => 90_000,
            'max_tenure_months'  => 18,
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
            'customer_number' => 'CU-P41-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'GL',
            'last_name'       => 'Borrower',
            'phone'           => '255712345850',
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P41-001',
            'status'                  => 'approved',
            'current_stage'           => 'disbursement',
            'requested_amount'        => 2_500_000,
            'requested_tenure_months' => 12,
        ]);

        \App\Models\AssetReservation::create([
            'customer_id'          => $customer->id,
            'loan_application_id'  => $application->id,
            'marketplace_asset_id' => $asset->id,
            'status'               => 'deposit_paid',
            'deposit_amount'       => 880_000,
            'deposit_status'       => 'paid',
        ]);

        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_application_id' => $application->id,
            'loan_number'         => 'LN-P41-001',
            'principal_amount'    => 2_500_000,
            'approved_amount'     => 2_500_000,
            'interest_rate'       => 0.15,
            'tenure_months'       => 12,
            'outstanding_balance' => 2_400_000,
            'status'              => 'active',
        ]);

        $repayment = Repayment::create([
            'loan_id'             => $loan->id,
            'reference'           => 'RCP-P41-001',
            'channel'             => 'mobile_money',
            'amount'              => 120_000,
            'status'              => 'received',
            'principal_component' => 80_000,
            'interest_component'  => 40_000,
            'paid_at'             => now(),
        ]);

        $entry = app(AssetLendingRepaymentGlService::class)->postSupplierPrincipalLiability($loan, $repayment);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => Repayment::class,
            'source_id'   => $repayment->id,
            'memo'        => 'managed_loan_supplier_payable',
        ]);
    }
}
