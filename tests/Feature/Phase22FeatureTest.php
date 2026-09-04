<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CapitalPartnerAllocationService;
use Database\Seeders\VendorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase22FeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{loan: Loan, primary: Lender, secondary: Lender} */
    private function priorityLoanFixtures(float $principal): array
    {
        $customer = Customer::create([
            'customer_number' => 'CU-P22-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Priority',
            'last_name' => 'Borrower',
            'phone' => '2557123459'.random_int(10, 99),
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-P22-'.random_int(100, 999),
            'name' => 'Priority Product',
            'is_active' => true,
            'uses_capital_partner' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $primary = Lender::create([
            'code' => 'LEND-P22-P-'.random_int(100, 999),
            'name' => 'Primary Capital Partner',
            'type' => 'institutional',
            'status' => 'active',
            'allocation_priority' => 1,
            'credit_limit' => 2_000_000,
            'available_balance' => 500_000,
        ]);

        FundingPool::create([
            'lender_id' => $primary->id,
            'name' => 'Primary pool',
            'status' => 'open',
            'amount_committed' => 500_000,
            'amount_deployed' => 0,
        ]);

        $secondary = Lender::create([
            'code' => 'LEND-P22-S-'.random_int(100, 999),
            'name' => 'Secondary Capital Partner',
            'type' => 'institutional',
            'status' => 'active',
            'allocation_priority' => 2,
            'credit_limit' => 10_000_000,
            'available_balance' => 5_000_000,
        ]);

        FundingPool::create([
            'lender_id' => $secondary->id,
            'name' => 'Secondary pool',
            'status' => 'open',
            'amount_committed' => 5_000_000,
            'amount_deployed' => 0,
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-P22-'.random_int(1000, 9999),
            'principal_amount' => $principal,
            'approved_amount' => $principal,
            'outstanding_balance' => $principal,
            'interest_rate' => 0.15,
            'tenure_months' => 12,
            'status' => 'pending_disbursement',
        ]);

        return compact('loan', 'primary', 'secondary');
    }

    public function test_priority_allocation_fills_highest_priority_partner_first(): void
    {
        Setting::set('finance.capital_allocation_strategy', 'priority');

        ['loan' => $loan, 'primary' => $primary, 'secondary' => $secondary] = $this->priorityLoanFixtures(1_500_000);

        app(CapitalPartnerAllocationService::class)->allocateForLoan($loan);

        $allocations = $loan->fresh()->capitalAllocations()->orderBy('lender_id')->get();

        $this->assertSame(2, $allocations->count());

        $primaryShare = (float) $allocations->firstWhere('lender_id', $primary->id)?->allocated_principal;
        $secondaryShare = (float) $allocations->firstWhere('lender_id', $secondary->id)?->allocated_principal;

        $this->assertEqualsWithDelta(500_000.0, $primaryShare, 0.01);
        $this->assertEqualsWithDelta(1_000_000.0, $secondaryShare, 0.01);
        $this->assertEqualsWithDelta(1_500_000.0, $primaryShare + $secondaryShare, 0.01);
    }

    public function test_priority_allocation_uses_single_partner_when_enough_capacity(): void
    {
        Setting::set('finance.capital_allocation_strategy', 'priority');

        ['loan' => $loan, 'primary' => $primary] = $this->priorityLoanFixtures(400_000);

        app(CapitalPartnerAllocationService::class)->allocateForLoan($loan);

        $this->assertSame(1, $loan->fresh()->capitalAllocations()->count());
        $this->assertSame(
            $primary->id,
            $loan->fresh()->capitalAllocations()->first()->lender_id
        );
        $this->assertEqualsWithDelta(400_000.0, (float) $loan->fresh()->capitalAllocations()->sum('allocated_principal'), 0.01);
    }

    public function test_partner_login_shows_ptr_code_placeholder(): void
    {
        $this->followingRedirects()
            ->get('/login/partner')
            ->assertOk()
            ->assertSee(__('site.auth.partner_sign_in'), false);
    }

    public function test_api_partner_creation_uses_ptr_prefix(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/vendors', [
            'name' => 'API Partner',
            'category' => 'supplier',
            'phone' => '255712345816',
        ])
            ->assertCreated();

        $partner = Vendor::query()->where('name', 'API Partner')->first();

        $this->assertNotNull($partner);
        $this->assertStringStartsWith('PT-', $partner->vendor_number);
    }

    public function test_vendor_seeder_uses_ptr_demo_codes(): void
    {
        $this->seed(VendorSeeder::class);

        $this->assertTrue(
            Vendor::query()->where('partner_number', 'like', 'PTR-DEMO-%')->exists()
        );
        $this->assertFalse(
            Vendor::query()->where('partner_number', 'like', 'VND-DEMO-%')->exists()
        );
    }
}
