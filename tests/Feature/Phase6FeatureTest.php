<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ApplicationDocumentRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_request_applies_preset_default_instructions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = LoanProduct::create([
            'code'              => 'IL-P6',
            'name'              => 'Installment',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-P6-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Test',
            'last_name'       => 'Borrower',
            'phone'           => '255712345699',
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number' => 'APP-P6-001',
            'status'             => 'submitted',
            'current_stage'      => 'credit_appraisal',
            'requested_amount'   => 1_000_000,
            'requested_tenure_months' => 12,
        ]);

        $request = app(ApplicationDocumentRequestService::class)->create(
            $application,
            $admin,
            'New Insurance Certificate',
            null,
        );

        $this->assertStringContainsString('insurance', strtolower((string) $request->instructions));
        $this->assertDatabaseHas('loan_application_document_requests', [
            'loan_application_id' => $application->id,
            'label'               => 'New Insurance Certificate',
            'status'              => 'pending',
        ]);
    }

    public function test_asset_backed_presets_include_insurance_and_ownership_requests(): void
    {
        $presets = ApplicationDocumentRequestService::ASSET_BACKED_PRESET_LABELS;

        $this->assertContains('New Insurance Certificate', $presets);
        $this->assertContains('New Ownership Document', $presets);
        $this->assertContains('New Asset Photo', $presets);
    }

    public function test_public_marketplace_shows_deposit_breakdown(): void
    {
        $asset = MarketplaceAsset::create([
            'slug'                   => 'p6-truck',
            'title'                  => 'Isuzu Truck',
            'category'               => 'vehicle',
            'supplier_name'          => 'Supplier',
            'asset_value'            => 5_000_000,
            'supplier_deposit'       => 1_000_000,
            'deposit_markup_percent' => 10,
            'customer_deposit'       => 1_100_000,
            'weekly_installment'     => 120_000,
            'max_tenure_months'      => 24,
            'is_active'              => true,
        ]);

        $this->get(route('site.marketplace.show', $asset->slug))
            ->assertOk()
            ->assertSee('Deposit breakdown', false)
            ->assertSee('Supplier deposit', false)
            ->assertSee('Company markup', false);
    }

    public function test_affiliate_dashboard_prompts_for_kyc_before_sharing(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        Vendor::create([
            'user_id'               => $user->id,
            'vendor_number'         => 'AFF-P6-001',
            'name'                  => 'Affiliate Partner',
            'category'              => 'affiliate',
            'status'                => 'active',
            'affiliate_code'        => 'KPA-P6',
            'affiliate_kyc_status'  => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('site.vendor.dashboard'))
            ->assertOk()
            ->assertSee('Complete affiliate KYC', false)
            ->assertDontSee('Copy message', false);
    }
}
