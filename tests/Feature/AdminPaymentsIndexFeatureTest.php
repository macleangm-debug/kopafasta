<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentsIndexFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $branch = Branch::create([
            'code' => 'PAY'.random_int(10, 99),
            'name' => 'Pay Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    private function customer(User $actor): Customer
    {
        return Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-PAY-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Pay',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $actor->branch_id,
        ]);
    }

    private function payment(Customer $customer, array $attrs): CustomerPayment
    {
        return CustomerPayment::create(array_merge([
            'reference' => 'PAY-'.random_int(10000, 99999),
            'customer_id' => $customer->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 5000,
            'currency' => 'TZS',
            'status' => 'verified',
            'payment_date' => now()->toDateString(),
            'verified_at' => now(),
        ], $attrs));
    }

    public function test_payments_defaults_to_complete_and_hides_in_flight_mobile(): void
    {
        $admin = $this->staff();
        $customer = $this->customer($admin);
        $product = \App\Models\LoanProduct::create([
            'code' => 'AL-'.random_int(100, 999),
            'name' => 'Asset Lending Loan',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $complete = $this->payment($customer, [
            'reference' => 'PAY-COMPLETE-5K',
            'status' => 'verified',
            'amount' => 5000,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
        ]);
        $inflight = $this->payment($customer, [
            'reference' => 'PAY-INFLIGHT',
            'status' => 'processing',
            'verified_at' => null,
        ]);
        $bankPending = $this->payment($customer, [
            'reference' => 'PAY-BANK-WAIT',
            'payment_method' => 'bank_transfer',
            'status' => 'pending_verification',
            'verified_at' => null,
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Payments', $html);
        $this->assertStringContainsString('Incoming · complete', $html);
        $this->assertStringContainsString('Outgoing · complete', $html);
        $this->assertStringContainsString('Awaiting bank verify', $html);
        $this->assertStringContainsString($complete->reference, $html);
        $this->assertStringContainsString('Application Fee', $html);
        $this->assertStringContainsString('Asset Lending Loan', $html);
        $this->assertStringContainsString('Mobile money', $html);
        $this->assertStringNotContainsString('>M-Pesa<', $html);
        $this->assertMatchesRegularExpression('/Incoming · complete[\s\S]*?5[,.]?000/', $html);
        $this->assertStringNotContainsString($inflight->reference, $html);
        $this->assertStringNotContainsString($bankPending->reference, $html);

        $bankHtml = $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.index', ['status' => 'awaiting_bank']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($bankPending->reference, $bankHtml);
        $this->assertStringNotContainsString($complete->reference, $bankHtml);
        $this->assertStringNotContainsString($inflight->reference, $bankHtml);
    }

    public function test_legacy_pending_query_redirects_filter_to_bank_queue(): void
    {
        $admin = $this->staff();
        $customer = $this->customer($admin);
        $bankPending = $this->payment($customer, [
            'reference' => 'PAY-LEGACY-BANK',
            'payment_method' => 'bank_transfer',
            'status' => 'pending_verification',
            'verified_at' => null,
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.index', ['status' => 'pending']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($bankPending->reference, $html);
    }

    public function test_mobile_money_method_shows_operator_not_generic_mpesa(): void
    {
        $admin = $this->staff();
        $customer = $this->customer($admin);
        $this->payment($customer, [
            'reference' => 'PAY-MIXX',
            'payment_method' => 'mobile_money',
            'provider_meta' => ['operator' => 'Tigo'],
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Mixx by Yas', $html);
        $this->assertStringNotContainsString('>M-Pesa<', $html);
    }
}
