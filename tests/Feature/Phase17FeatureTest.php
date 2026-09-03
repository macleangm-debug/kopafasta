<?php

namespace Tests\Feature;

use App\Models\AssetRequest;
use App\Models\Customer;
use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\PartnerApplication;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CapitalPartnerAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase17FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_capital_allocation_strategy_defaults_to_proportional(): void
    {
        $this->assertSame('proportional', app(CapitalPartnerAllocationService::class)->allocationStrategy());
    }

    /** @return array{lender: Lender, loan: Loan} */
    private function capitalLoanFixtures(float $principal): array
    {
        $customer = Customer::create([
            'customer_number' => 'CU-P17-'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Capital',
            'last_name'       => 'Test',
            'phone'           => '2557123458'.random_int(10, 99),
        ]);

        $product = LoanProduct::create([
            'code'                 => 'IL-P17-'.random_int(100, 999),
            'name'                 => 'Capital Product',
            'is_active'            => true,
            'uses_capital_partner' => true,
            'interest_rate'        => 0.15,
            'min_amount'           => 100_000,
            'max_amount'           => 5_000_000,
            'tenure_min_months'    => 3,
            'tenure_max_months'    => 24,
        ]);

        $lender = Lender::create([
            'code'              => 'LEND-P17-'.random_int(100, 999),
            'name'              => 'Capital Partner',
            'type'              => 'institutional',
            'status'            => 'active',
            'credit_limit'      => 10_000_000,
            'available_balance' => 5_000_000,
        ]);

        FundingPool::create([
            'lender_id'        => $lender->id,
            'name'             => 'Main pool',
            'status'           => 'open',
            'amount_committed' => 5_000_000,
            'amount_deployed'  => 0,
        ]);

        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_number'         => 'LN-P17-'.random_int(1000, 9999),
            'principal_amount'    => $principal,
            'approved_amount'     => $principal,
            'outstanding_balance' => $principal,
            'interest_rate'       => 0.15,
            'tenure_months'       => 12,
            'status'              => 'pending',
        ]);

        return compact('lender', 'loan');
    }

    public function test_manual_allocation_strategy_blocks_automatic_allocation(): void
    {
        Setting::set('finance.capital_allocation_strategy', 'manual');

        ['loan' => $loan] = $this->capitalLoanFixtures(1_000_000);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CapitalPartnerAllocationService::class)->allocateForLoan($loan);
    }

    public function test_manual_allocation_can_be_assigned_then_disbursed(): void
    {
        Setting::set('finance.capital_allocation_strategy', 'manual');

        ['lender' => $lender, 'loan' => $loan] = $this->capitalLoanFixtures(500_000);

        app(CapitalPartnerAllocationService::class)->allocateManually($loan, [
            ['lender_id' => $lender->id, 'amount' => 500_000],
        ]);

        $this->assertSame(1, $loan->fresh()->capitalAllocations()->count());
        $this->assertEqualsWithDelta(500_000.0, (float) $loan->fresh()->capitalAllocations()->sum('allocated_principal'), 0.01);

        app(CapitalPartnerAllocationService::class)->allocateForLoan($loan->fresh());
        $this->assertSame(1, $loan->fresh()->capitalAllocations()->count());
    }

    public function test_manual_allocation_rejects_totals_not_matching_principal(): void
    {
        Setting::set('finance.capital_allocation_strategy', 'manual');

        ['lender' => $lender, 'loan' => $loan] = $this->capitalLoanFixtures(500_000);

        try {
            app(CapitalPartnerAllocationService::class)->allocateManually($loan, [
                ['lender_id' => $lender->id, 'amount' => 400_000],
            ]);
            $this->fail('Expected validation exception for mismatched total.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('allocations', $e->errors());
        }
    }

    public function test_round_robin_allocation_assigns_full_loan_to_one_partner_when_possible(): void
    {
        Setting::set('finance.capital_allocation_strategy', 'round_robin');

        ['loan' => $loan] = $this->capitalLoanFixtures(500_000);

        app(CapitalPartnerAllocationService::class)->allocateForLoan($loan);

        $this->assertSame(1, $loan->fresh()->capitalAllocations()->count());
        $this->assertEqualsWithDelta(500_000.0, (float) $loan->fresh()->capitalAllocations()->sum('allocated_principal'), 0.01);
    }

    public function test_public_affiliate_application_can_be_submitted(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->post(route('site.affiliate.apply.post'), [
            'applicant_category' => 'individual',
            'full_name'     => 'Affiliate Applicant',
            'email'         => 'affiliate@example.com',
            'phone'         => '+255712345800',
            'region'        => 'Dar es Salaam',
            'occupation'    => 'Shop owner',
            'sales_experience' => 'I sell airtime and assist customers daily.',
            'languages'     => ['sw', 'en'],
            'why_affiliate' => 'I already advise customers on mobile money.',
            'acquisition_methods' => ['existing_customers', 'community'],
            'monthly_reach' => '11-30',
            'first_10_customers' => 'I will start with my regular shop customers this month.',
            'declaration_accurate' => '1',
            'declaration_standards' => '1',
            'declaration_no_fees' => '1',
            'declaration_not_employment' => '1',
            'doc_national_id_front' => \Illuminate\Http\UploadedFile::fake()->image('id-front.jpg'),
            'doc_national_id_back' => \Illuminate\Http\UploadedFile::fake()->image('id-back.jpg'),
        ])->assertRedirect(route('site.partners.apply.tracking', ['phone' => '+255712345800']));

        $this->assertDatabaseHas('partner_applications', [
            'email'  => 'affiliate@example.com',
            'status' => 'pending',
            'type'   => 'affiliate',
        ]);
    }

    public function test_admin_can_review_affiliate_applications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        PartnerApplication::create([
            'type'      => 'affiliate',
            'full_name' => 'Review Me',
            'email'     => 'review@example.com',
            'phone'     => '+255712345801',
            'status'    => 'pending',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partner-applications.index'))
            ->assertOk()
            ->assertSee('Review Me', false);
    }

    public function test_legacy_vendor_show_redirects_to_partners_show(): void
    {
        $partner = Vendor::create([
            'vendor_number' => 'PTR-P17-001',
            'name'          => 'Legacy Partner',
            'category'      => 'supplier',
            'status'        => 'active',
            'phone'         => '255712345802',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.vendors.show', $partner))
            ->assertRedirect(route('admin.partners.show', $partner));
    }

    public function test_legacy_vendor_list_route_redirects_to_partners_hub(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.vendors.suppliers'))
            ->assertRedirect('/admin/partners/suppliers');
    }

    public function test_asset_request_admin_page_shows_create_listing_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $customer = Customer::create([
            'customer_number' => 'CU-P17-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Request',
            'last_name'       => 'Borrower',
            'phone'           => '255712345803',
        ]);

        AssetRequest::create([
            'customer_id'             => $customer->id,
            'asset_name'              => 'Custom Motorbike',
            'description'             => 'Need a 150cc bike for delivery work',
            'budget'                  => 2_500_000,
            'preferred_tenure_months' => 18,
            'status'                  => 'pending',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.asset-requests.index'))
            ->assertOk()
            ->assertSee('Custom Motorbike', false)
            ->assertSee('Create listing', false);

        $this->assertTrue(Route::has('admin.partner-applications.index'));
    }
}
