<?php

namespace Tests\Feature;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetRetakeReviewFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $number): Customer
    {
        return Customer::create([
            'customer_number' => $number,
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Retake',
            'last_name'       => 'Borrower',
            'phone'           => '25571230'.substr($number, -4),
        ]);
    }

    public function test_marketplace_asset_review_shows_gallery_and_retake_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = $this->makeCustomer('CU-RETAKE-001');

        $product = LoanProduct::create([
            'code'              => 'AL',
            'name'              => 'Asset Lending',
            'is_active'         => true,
            'interest_rate'     => 0.12,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 24,
        ]);

        $asset = MarketplaceAsset::create([
            'slug'             => 'retake-asset',
            'category'         => 'vehicle',
            'title'            => 'Test Motorcycle',
            'supplier_name'    => 'Test Supplier',
            'asset_value'      => 3_000_000,
            'supplier_deposit' => 300_000,
            'customer_deposit' => 350_000,
            'photos'           => ['marketplace/asset-1.jpg', 'marketplace/asset-2.jpg'],
            'is_active'        => true,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-RETAKE-001',
            'status'                  => 'under_review',
            'current_stage'           => 'screening',
            'requested_amount'        => 350_000,
            'requested_tenure_months' => 6,
        ]);

        AssetReservation::create([
            'customer_id'           => $customer->id,
            'marketplace_asset_id'  => $asset->id,
            'loan_application_id'   => $application->id,
            'status'                => 'application_submitted',
            'reservation_fee_status'=> 'paid',
            'deposit_amount'        => 350_000,
            'deposit_status'        => 'paid',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $application).'?workspace=profiles&tab=collateral')
            ->assertOk()
            ->assertSee('Request all asset photos retaken', false)
            ->assertSee('Photo 1', false)
            ->assertSee('Need face photo retake?', false);
    }

    public function test_asset_backed_collateral_review_shows_gallery_and_retake_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = $this->makeCustomer('CU-RETAKE-002');

        $product = LoanProduct::create([
            'code'              => 'AB',
            'name'              => 'Asset Backed Loan',
            'is_active'         => true,
            'interest_rate'     => 0.12,
            'min_amount'        => 100_000,
            'max_amount'        => 10_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 24,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-RETAKE-002',
            'status'                  => 'under_review',
            'current_stage'           => 'credit_appraisal',
            'requested_amount'        => 1_000_000,
            'requested_tenure_months' => 12,
        ]);

        $customerAsset = CustomerAsset::create([
            'customer_id'          => $customer->id,
            'asset_type'           => 'vehicle',
            'label'                => 'Toyota Hilux',
            'registration_number'  => 'T123ABC',
            'estimated_value'      => 15_000_000,
            'photo_paths'          => ['customer/1/assets/front.jpg', 'customer/1/assets/back.jpg'],
            'metadata'             => [
                'ownership_document_path' => 'customer/1/assets/docs/ownership.jpg',
                'insurance_document_path' => 'customer/1/assets/docs/insurance.jpg',
            ],
            'is_active'            => true,
        ]);

        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id'   => $customerAsset->id,
            'asset_type'          => 'vehicle',
            'description'         => 'Toyota Hilux collateral',
            'valuation_status'    => 'awaiting_valuation',
            'uw_status'           => LoanApplicationAsset::UW_PENDING,
            'is_primary'          => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $application).'?workspace=profiles&tab=collateral')
            ->assertOk()
            ->assertSee('Asset-backed collateral', false)
            ->assertSee('Request all asset photos retaken', false)
            ->assertSee('Request ownership document', false)
            ->assertSee('Request insurance certificate', false)
            ->assertSee('Need face photo retake?', false);
    }
}
