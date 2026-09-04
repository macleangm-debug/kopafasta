<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase29FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P29-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Complete',
            'last_name' => 'Borrower',
            'phone' => '2557123466'.random_int(10, 99),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    private function activeLoan(Customer $customer): Loan
    {
        $product = LoanProduct::create([
            'code' => 'IL-P29',
            'name' => 'Phase 29 Product',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        return Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-P29-'.random_int(1000, 9999),
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'outstanding_balance' => 450_000,
            'interest_rate' => 0.15,
            'tenure_months' => 12,
            'status' => 'active',
        ]);
    }

    public function test_swahili_payments_schedule_and_loan_profile_strings_are_available(): void
    {
        $this->assertSame(
            'Fanya malipo',
            __('borrower.payments_page.create.title', [], 'sw')
        );
        $this->assertSame(
            'Ratiba ya malipo',
            __('borrower.schedule_page.title', [], 'sw')
        );
        $this->assertSame(
            'Taarifa za kibinafsi',
            __('borrower.loan_profile.sections.personal', [], 'sw')
        );
        $this->assertSame(
            'Rudi kwenye maombi',
            __('borrower.guarantor.back_to_requests', [], 'sw')
        );
    }

    public function test_payments_create_uses_wide_layout_and_translated_copy(): void
    {
        $customer = $this->completeBorrower();
        $this->activeLoan($customer);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.payments.create'))
            ->assertOk()
            ->assertSee(__('borrower.payments_page.create.title'), false);
    }

    public function test_schedule_page_uses_wide_layout_and_translated_copy(): void
    {
        $customer = $this->completeBorrower();
        $loan = $this->activeLoan($customer);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.schedule', $loan))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.schedule_page.title'), false)
            ->assertSee(__('borrower.schedule_page.empty'), false);
    }

    public function test_loan_show_page_uses_wide_content_layout(): void
    {
        $customer = $this->completeBorrower();
        $loan = $this->activeLoan($customer);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.loans.show', $loan))
            ->assertOk()
            ->assertSee('max-w-7xl', false);
    }

    public function test_payment_show_page_uses_translated_labels(): void
    {
        $customer = $this->completeBorrower();
        $loan = $this->activeLoan($customer);

        $payment = CustomerPayment::create([
            'reference' => 'PAY-P29-'.random_int(100, 999),
            'customer_id' => $customer->id,
            'payment_type' => 'loan_repayment',
            'payment_method' => 'mobile_money',
            'amount' => 50_000,
            'status' => 'pending_verification',
            'loan_id' => $loan->id,
            'loan_product_id' => $loan->loan_product_id,
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.payments_page.show.payment_reference'), false)
            ->assertSee(__('borrower.payments_page.show.amount'), false);
    }
}
