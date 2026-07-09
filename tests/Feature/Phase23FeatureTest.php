<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\CapitalPartnerAllocationService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase23FeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{loan: Loan, lenders: array<int, Lender>} */
    private function proportionalLoanFixtures(float $principal): array
    {
        $customer = Customer::create([
            'customer_number' => 'CU-P23-'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Proportional',
            'last_name'       => 'Borrower',
            'phone'           => '2557123460'.random_int(10, 99),
        ]);

        $product = LoanProduct::create([
            'code'                 => 'IL-P23-'.random_int(100, 999),
            'name'                 => 'Proportional Product',
            'is_active'            => true,
            'uses_capital_partner' => true,
            'interest_rate'        => 0.15,
            'min_amount'           => 100_000,
            'max_amount'           => 5_000_000,
            'tenure_min_months'    => 3,
            'tenure_max_months'    => 24,
        ]);

        $lenderA = Lender::create([
            'code'              => 'LEND-P23-A-'.random_int(100, 999),
            'name'              => 'Partner A',
            'type'              => 'institutional',
            'status'            => 'active',
            'credit_limit'      => 5_000_000,
            'available_balance' => 1_000_000,
        ]);

        FundingPool::create([
            'lender_id'        => $lenderA->id,
            'name'             => 'Pool A',
            'status'           => 'open',
            'amount_committed' => 1_000_000,
            'amount_deployed'  => 0,
        ]);

        $lenderB = Lender::create([
            'code'              => 'LEND-P23-B-'.random_int(100, 999),
            'name'              => 'Partner B',
            'type'              => 'institutional',
            'status'            => 'active',
            'credit_limit'      => 5_000_000,
            'available_balance' => 3_000_000,
        ]);

        FundingPool::create([
            'lender_id'        => $lenderB->id,
            'name'             => 'Pool B',
            'status'           => 'open',
            'amount_committed' => 3_000_000,
            'amount_deployed'  => 0,
        ]);

        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_number'         => 'LN-P23-'.random_int(1000, 9999),
            'principal_amount'    => $principal,
            'approved_amount'     => $principal,
            'outstanding_balance' => $principal,
            'interest_rate'       => 0.15,
            'tenure_months'       => 12,
            'status'              => 'pending_disbursement',
        ]);

        return ['loan' => $loan, 'lenders' => [$lenderA, $lenderB]];
    }

    public function test_proportional_allocation_splits_by_available_capacity(): void
    {
        Setting::set('finance.capital_allocation_strategy', 'proportional');

        ['loan' => $loan, 'lenders' => [$lenderA, $lenderB]] = $this->proportionalLoanFixtures(1_000_000);

        app(CapitalPartnerAllocationService::class)->allocateForLoan($loan);

        $allocations = $loan->fresh()->capitalAllocations()->get();

        $this->assertSame(2, $allocations->count());

        $shareA = (float) $allocations->firstWhere('lender_id', $lenderA->id)?->allocated_principal;
        $shareB = (float) $allocations->firstWhere('lender_id', $lenderB->id)?->allocated_principal;

        $this->assertEqualsWithDelta(250_000.0, $shareA, 0.01);
        $this->assertEqualsWithDelta(750_000.0, $shareB, 0.01);
        $this->assertEqualsWithDelta(1_000_000.0, $shareA + $shareB, 0.01);
    }

    public function test_apply_wizard_shows_submit_requirements_hint_when_profile_incomplete(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P23-INCOMPLETE',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Incomplete',
            'last_name'             => 'Applicant',
            'phone'                 => '255712346017',
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        LoanProduct::create([
            'code'              => 'IL-P23-APPLY',
            'name'              => 'Apply Test Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.loan-products'))
            ->assertOk()
            ->assertSee(__('borrower.apply.kyc_incomplete_hint'), false);
    }

    public function test_swahili_application_fee_strings_are_available(): void
    {
        $this->assertSame(
            'Ada ya maombi',
            __('borrower.apply.application_fee.title', [], 'sw')
        );
        $this->assertSame(
            'Kamilisha mahitaji haya kabla ya kulipa ada ya maombi:',
            __('borrower.apply.application_fee.requirements_before_fee', [], 'sw')
        );
    }

    public function test_partner_performance_report_route_redirects_to_legacy_view(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue(Route::has('admin.reports.partner-performance'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.partner-performance'))
            ->assertRedirect(route('admin.reports.vendor-performance'));
    }

    public function test_borrower_dashboard_uses_wide_content_layout(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-P23-DASH',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Dash',
            'last_name'       => 'Board',
            'phone'           => '255712346018',
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee('max-w-7xl', false);
    }
}
