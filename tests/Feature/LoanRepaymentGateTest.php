<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanRepaymentGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_loan_profile_pay_opens_shared_repayment_gate(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        $customer = Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-REP-'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Repay',
            'last_name'       => 'Borrower',
            'phone'           => '2557123499'.random_int(10, 99),
            'country_code'    => 'TZ',
        ]);

        $product = LoanProduct::create([
            'code'              => 'PLR'.random_int(10, 99),
            'name'              => 'Repay Product',
            'category'          => 'personal',
            'is_active'         => true,
            'interest_rate'     => 0.03,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_number'         => 'LN-REP-'.random_int(1000, 9999),
            'principal_amount'    => 500_000,
            'approved_amount'     => 500_000,
            'outstanding_balance' => 400_000,
            'interest_rate'       => 0.15,
            'tenure_months'       => 12,
            'status'              => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.payments.create', ['loan' => $loan->id]))
            ->assertOk()
            ->assertSee(__('borrower.payments_page.create.title'), false)
            ->assertSee(__('borrower.payments_page.create.mobile_money'), false)
            ->assertSee(__('borrower.payments_page.create.bank_transfer'), false)
            ->assertSee('name="loan_id"', false)
            ->assertSee((string) $loan->id, false);
    }
}
