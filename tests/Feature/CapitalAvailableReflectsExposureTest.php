<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\LoanCapitalAllocation;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\CapitalPartnerMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapitalAvailableReflectsExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_capital_subtracts_outstanding_exposure(): void
    {
        $user = User::factory()->create(['role' => 'investor']);
        $lender = Lender::query()->create([
            'user_id' => $user->id,
            'code' => 'TESTCAP',
            'name' => 'Test Capital',
            'type' => 'institutional',
            'status' => 'active',
            'credit_limit' => 50_000_000,
            'available_balance' => 50_000_000,
        ]);

        $pool = FundingPool::query()->create([
            'lender_id' => $lender->id,
            'name' => 'Primary',
            'currency' => 'TZS',
            'amount_committed' => 50_000_000,
            'amount_deployed' => 0,
            'status' => 'open',
        ]);

        $customer = Customer::query()->create([
            'customer_number' => 'CUS-CAP-1',
            'first_name' => 'Test',
            'last_name' => 'Borrower',
            'phone' => '+255710009001',
            'status' => 'active',
        ]);

        $product = LoanProduct::query()->create([
            'name' => 'Test Product',
            'code' => 'TP-CAP',
            'is_active' => true,
            'min_amount' => 10000,
            'max_amount' => 1000000,
            'interest_rate' => 0.15,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $loan = Loan::query()->create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-CAP-1',
            'principal_amount' => 50_000,
            'approved_amount' => 50_000,
            'outstanding_balance' => 50_000,
            'interest_rate' => 0.15,
            'tenure_months' => 12,
            'status' => 'active',
        ]);

        LoanCapitalAllocation::query()->create([
            'loan_id' => $loan->id,
            'lender_id' => $lender->id,
            'funding_pool_id' => $pool->id,
            'allocated_principal' => 50_000,
            'allocation_percent' => 100,
            'partner_interest_share_percent' => 60,
            'company_interest_share_percent' => 40,
            'outstanding_exposure' => 50_000,
            'interest_earned_partner' => 0,
            'interest_earned_company' => 0,
        ]);

        $metrics = app(CapitalPartnerMetricsService::class)->forLender($lender->fresh('pools'));

        $this->assertSame(50_000_000.0, (float) $metrics['capital_invested']);
        $this->assertSame(50_000.0, (float) $metrics['capital_utilized']);
        $this->assertSame(49_950_000.0, (float) $metrics['capital_available']);
    }
}
