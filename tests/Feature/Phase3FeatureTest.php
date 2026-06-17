<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanApplicationPostApprovalFee;
use App\Models\LoanProduct;
use App\Models\LoanProductPostApprovalFee;
use App\Models\MarketplaceAsset;
use App\Services\PostApprovalFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_locked_marketplace_assets_are_hidden_from_browse(): void
    {
        MarketplaceAsset::create([
            'slug'                => 'available-bike',
            'title'               => 'Available Bike',
            'category'            => 'motorcycle',
            'supplier_name'       => 'Supplier A',
            'asset_value'         => 1_000_000,
            'customer_deposit'    => 200_000,
            'weekly_installment'  => 25_000,
            'max_tenure_months'   => 12,
            'availability_status' => 'available',
            'is_active'           => true,
        ]);

        MarketplaceAsset::create([
            'slug'                => 'locked-bike',
            'title'               => 'Locked Bike',
            'category'            => 'motorcycle',
            'supplier_name'       => 'Supplier B',
            'asset_value'         => 1_200_000,
            'customer_deposit'    => 250_000,
            'weekly_installment'  => 30_000,
            'max_tenure_months'   => 12,
            'availability_status' => 'locked',
            'is_active'           => true,
        ]);

        $response = $this->get(route('site.marketplace'));

        $response->assertOk();
        $response->assertSee('Available Bike');
        $response->assertDontSee('Locked Bike');
    }

    public function test_post_approval_fees_regenerate_when_product_config_changes(): void
    {
        $product = LoanProduct::create([
            'code'               => 'PL',
            'name'               => 'Personal Loan',
            'is_active'          => true,
            'interest_rate'      => 3.5,
            'min_amount'         => 100_000,
            'max_amount'         => 5_000_000,
            'tenure_min_months'  => 3,
            'tenure_max_months'  => 24,
        ]);

        $template = LoanProductPostApprovalFee::create([
            'loan_product_id' => $product->id,
            'code'            => 'DOC',
            'name'            => 'Documentation fee',
            'fee_type'        => 'fixed',
            'amount'          => 50_000,
            'sort_order'      => 1,
            'is_active'       => true,
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-PAF-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Fee',
            'last_name'       => 'Tester',
            'phone'           => '255712345679',
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-PAF-001',
            'status'                  => 'approved',
            'current_stage'           => 'approval',
            'requested_amount'        => 1_000_000,
            'requested_tenure_months' => 12,
        ]);

        $service = app(PostApprovalFeeService::class);
        $service->generateForApplication($application);

        $this->assertSame(50_000.0, (float) LoanApplicationPostApprovalFee::query()
            ->where('loan_application_id', $application->id)
            ->value('calculated_amount'));

        $template->update(['amount' => 75_000]);

        $regenerated = $service->syncFromProductUpdate($product->fresh());

        $this->assertSame(1, $regenerated);
        $this->assertSame(75_000.0, (float) LoanApplicationPostApprovalFee::query()
            ->where('loan_application_id', $application->id)
            ->whereNull('manual_post_approval_fee_id')
            ->value('calculated_amount'));
    }

    public function test_regenerate_skips_applications_with_paid_template_fees(): void
    {
        $product = LoanProduct::create([
            'code'               => 'PL2',
            'name'               => 'Personal Loan 2',
            'is_active'          => true,
            'interest_rate'      => 3.5,
            'min_amount'         => 100_000,
            'max_amount'         => 5_000_000,
            'tenure_min_months'  => 3,
            'tenure_max_months'  => 24,
        ]);

        LoanProductPostApprovalFee::create([
            'loan_product_id' => $product->id,
            'code'            => 'INS',
            'name'            => 'Insurance',
            'fee_type'        => 'fixed',
            'amount'          => 40_000,
            'sort_order'      => 1,
            'is_active'       => true,
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-PAF-002',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Paid',
            'last_name'       => 'Fee',
            'phone'           => '255712345680',
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-PAF-002',
            'status'                  => 'approved',
            'current_stage'           => 'approval',
            'requested_amount'        => 800_000,
            'requested_tenure_months' => 12,
        ]);

        $service = app(PostApprovalFeeService::class);
        $service->generateForApplication($application);

        LoanApplicationPostApprovalFee::query()
            ->where('loan_application_id', $application->id)
            ->update(['status' => 'paid', 'amount_paid' => 40_000, 'paid_at' => now()]);

        $this->assertFalse($service->canRegenerateTemplateFees($application->fresh()));
        $this->assertSame(0, $service->syncFromProductUpdate($product));
    }
}
