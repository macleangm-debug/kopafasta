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

    public function test_underwriting_requests_surface_as_guided_borrower_ctas(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = LoanProduct::create([
            'code'              => 'IL-P6-UW',
            'name'              => 'Installment UW',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-P6-UW',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Guided',
            'last_name'       => 'Borrower',
            'phone'           => '255712345698',
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P6-UW',
            'status'                  => 'submitted',
            'current_stage'           => 'credit_appraisal',
            'requested_amount'        => 1_000_000,
            'requested_tenure_months' => 12,
        ]);

        $svc = app(ApplicationDocumentRequestService::class);
        $svc->create($application, $admin, 'Signature Not Visible');
        $svc->create($application, $admin, 'New face verification photo');
        $svc->create($application, $admin, 'Updated Bank Statement');

        $application->refresh()->load('documentRequests');
        $actions = $svc->openGuidedActionsForApplication($application);

        $this->assertCount(3, $actions);
        $this->assertSame('signature', $actions[0]['kind']);
        $this->assertStringContainsString('signature', $actions[0]['url']);
        $this->assertSame('face', $actions[1]['kind']);
        $this->assertStringContainsString('face', $actions[1]['url']);
        $this->assertSame('income', $actions[2]['kind']);
        $this->assertStringContainsString('income', $actions[2]['url']);

        $next = app(\App\Services\LoanApplicationNextActionService::class)
            ->forApplication($customer, $application->fresh());

        $this->assertSame('upload_documents', $next['code']);
        $this->assertSame($actions[0]['url'], $next['url']);
        $this->assertSame($actions[0]['cta_label'], $next['button_label']);

        $profile = app(\App\Services\LoanApplicationProfileService::class)
            ->forApplication($customer, $application->fresh());
        $this->assertCount(3, $profile['underwriting_actions']);

        $row = app(\App\Services\BorrowerApplicationsDashboardService::class)
            ->formatSubmitted($application->fresh()->load(['product', 'documentRequests', 'loan']));
        $this->assertNotEmpty($row['underwriting_actions']);
        $this->assertSame(route('site.borrower.application', $application->id), $row['action_url']);
        $this->assertSame(__('borrower.applications_list.view'), $row['action_label']);
    }

    public function test_public_marketplace_hides_internal_deposit_breakdown(): void
    {
        MarketplaceAsset::create([
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

        $this->get(route('site.marketplace.show', 'p6-truck'))
            ->assertOk()
            ->assertSee('Isuzu Truck', false)
            ->assertDontSee('Deposit breakdown', false)
            ->assertDontSee('Company markup', false)
            ->assertDontSee('Supplier deposit', false);
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
