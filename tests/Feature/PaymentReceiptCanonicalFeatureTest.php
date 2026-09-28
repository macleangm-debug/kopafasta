<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\CustomerPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReceiptCanonicalFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_payment_uses_one_receipt_payload_on_show_list_and_pdf(): void
    {
        Setting::set('company.legal_name', 'Kopafasta Microfinance Limited');
        Setting::set('company.email', 'support@kopafasta.com');
        Setting::set('company.phone', '255700000000');
        Setting::set('company.website', 'https://www.kopafasta.com');

        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'MBR-RCPT-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Receipt',
            'last_name' => 'Member',
            'phone' => '255712345678',
        ]);
        $product = LoanProduct::create([
            'code' => 'IL-RCPT',
            'name' => 'Receipt Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-RCPT-1',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'ready_for_screening',
            'submitted_at' => now(),
        ]);
        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 15_000,
            'currency' => 'TZS',
            'status' => 'verified',
            'reference' => 'PAY-RCPT-1',
            'provider_ref' => 'PSP-99',
            'mobile_number' => '255712345678',
            'paid_at' => now(),
            'verified_at' => now(),
            'source_type' => LoanApplication::class,
            'source_id' => $application->id,
        ]);

        $payload = app(CustomerPaymentService::class)->receiptPayload($payment);
        $this->assertSame('PAY-RCPT-1', $payload['reference']);
        $this->assertSame('PSP-99', $payload['provider_ref']);
        $this->assertSame('MBR-RCPT-1', $payload['member_number']);
        $this->assertSame('APP-RCPT-1', $payload['application_number']);
        $this->assertSame('Kopafasta Microfinance Limited', $payload['legal_name']);
        $this->assertStringContainsString('proof of payment', $payload['keep_line']);

        $this->actingAs($user)
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertSee('kf-payment-receipt', false)
            ->assertSee('PAY-RCPT-1', false)
            ->assertDontSee('PSP-99', false)
            ->assertDontSee(__('borrower.payments_page.show.provider_reference'), false)
            ->assertSee('APP-RCPT-1', false)
            ->assertSee('MBR-RCPT-1', false)
            ->assertSee('Kopafasta Microfinance Limited', false)
            ->assertSee(__('borrower.payments_page.show.keep_receipt'), false)
            ->assertSee(__('borrower.payments_page.show.save_receipt'), false)
            ->assertSee(route('site.borrower.payments.receipt', $payment), false);

        $this->actingAs($user)
            ->get(route('site.borrower.payments'))
            ->assertOk()
            ->assertSee(__('borrower.payments_page.view_receipt'), false)
            ->assertSee('PAY-RCPT-1', false);

        $pdf = $this->actingAs($user)
            ->get(route('site.borrower.payments.receipt', $payment));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
    }

    public function test_unverified_payment_has_no_completed_receipt(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'MBR-RCPT-2',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Pending',
            'last_name' => 'Pay',
            'phone' => '255712345679',
        ]);
        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 15_000,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'reference' => 'PAY-RCPT-2',
            'mobile_number' => '255712345679',
        ]);

        $this->assertFalse(app(CustomerPaymentService::class)->canIssueReceipt($payment));

        $this->actingAs($user)
            ->get(route('site.borrower.payments'))
            ->assertOk()
            ->assertSee('PAY-RCPT-2', false)
            ->assertDontSee(__('borrower.payments_page.view_receipt'), false);

        $this->actingAs($user)
            ->get(route('site.borrower.payments.receipt', $payment))
            ->assertNotFound();
    }
}
