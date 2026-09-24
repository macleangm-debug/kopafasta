<?php

namespace Tests\Feature;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\Repayment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AssetLendingRepaymentService;
use App\Services\AssetLendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetLendingCommercialModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_snapshots_service_collection_and_ignores_later_supplier_change(): void
    {
        [$application, $vendor] = $this->marketplaceFile('managed_loan');
        $lending = app(AssetLendingService::class);

        $snapshot = $lending->snapshotCommercialTerms($application->fresh(['assetReservation.asset.vendor', 'product']));
        $this->assertSame('managed_loan', $snapshot['supplier_arrangement'] ?? null);
        $this->assertSame('Service / Collection', $snapshot['supplier_arrangement_label'] ?? null);
        $this->assertFalse((bool) ($snapshot['valuation_applicable'] ?? true));
        $this->assertSame($vendor->id, $snapshot['supplier_id'] ?? null);
        $this->assertNotEmpty($snapshot['product_settings_version'] ?? null);

        $vendor->update(['supplier_type' => 'upfront_settlement']);
        $again = $lending->snapshotCommercialTerms($application->fresh(['assetReservation.asset.vendor', 'product']));
        $this->assertSame('managed_loan', $again['supplier_arrangement'] ?? null);
        $this->assertTrue($lending->isServiceCollectionApplication($application->fresh()));
    }

    public function test_capital_funded_snapshot_blocks_later_supplier_principal(): void
    {
        [$application, $vendor] = $this->marketplaceFile('upfront_settlement');
        $lending = app(AssetLendingService::class);
        $lending->snapshotCommercialTerms($application->fresh(['assetReservation.asset.vendor', 'product']));

        $vendor->update(['supplier_type' => 'managed_loan']);

        $loan = Loan::create([
            'customer_id' => $application->customer_id,
            'loan_product_id' => $application->loan_product_id,
            'loan_application_id' => $application->id,
            'loan_number' => 'LN-AL-MODE-1',
            'principal_amount' => 3_000_000,
            'approved_amount' => 3_000_000,
            'interest_rate' => 0.15,
            'tenure_months' => 6,
            'outstanding_balance' => 3_000_000,
            'status' => 'active',
        ]);
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'reference' => 'RCP-AL-MODE-1',
            'channel' => 'mobile_money',
            'amount' => 150_000,
            'status' => 'received',
            'principal_component' => 100_000,
            'interest_component' => 50_000,
            'paid_at' => now(),
        ]);

        $this->assertSame('upfront_settlement', $lending->resolvedArrangement($application->fresh()));
        $this->assertNull(app(AssetLendingRepaymentService::class)->accruePrincipalPayout($loan->fresh(['application', 'product']), $repayment));
    }

    /** @return array{0: LoanApplication, 1: Vendor} */
    private function marketplaceFile(string $arrangement): array
    {
        User::factory()->create(['role' => 'admin']);
        $vendor = Vendor::create([
            'vendor_number' => 'PTR-AL-'.$arrangement,
            'name' => 'AL Supplier',
            'category' => 'supplier',
            'status' => 'active',
            'supplier_type' => $arrangement,
            'regions' => ['Dar es Salaam'],
        ]);
        $asset = MarketplaceAsset::create([
            'vendor_id' => $vendor->id,
            'slug' => 'al-'.$arrangement,
            'title' => 'Bajaji',
            'category' => 'vehicle',
            'supplier_name' => $vendor->name,
            'asset_value' => 20_000_000,
            'supplier_deposit' => 1_200_000,
            'customer_deposit' => 1_200_000,
            'weekly_installment' => 90_000,
            'max_tenure_months' => 6,
            'is_active' => true,
        ]);
        $product = LoanProduct::query()->where('code', 'AL')->first()
            ?? LoanProduct::create([
                'code' => 'AL',
                'name' => 'Asset Lending',
                'category' => 'asset_finance',
                'is_active' => true,
                'interest_rate' => 0.15,
                'min_amount' => 500_000,
                'max_amount' => 50_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 6,
                'uses_capital_partner' => false,
            ]);
        $customer = Customer::create([
            'customer_number' => 'CU-AL-'.$arrangement,
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asset',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-AL-'.$arrangement,
            'status' => 'approved',
            'current_stage' => 'disbursement',
            'requested_amount' => 18_800_000,
            'requested_tenure_months' => 6,
            'application_fee_amount' => 10_000,
        ]);
        AssetReservation::create([
            'customer_id' => $customer->id,
            'loan_application_id' => $application->id,
            'marketplace_asset_id' => $asset->id,
            'status' => 'deposit_paid',
            'deposit_amount' => 1_200_000,
            'deposit_status' => 'paid',
        ]);

        return [$application, $vendor];
    }
}
