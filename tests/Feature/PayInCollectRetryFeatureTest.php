<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Setting;
use App\Models\User;
use App\Services\CustomerPaymentService;
use App\Services\PayInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayInCollectRetryFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Customer, 2: CustomerPayment} */
    private function awaitingPayment(): array
    {
        Setting::setMany([
            'payin.enabled' => true,
            'payin.environment' => 'sandbox',
            'payin.api_key' => 'pk_test',
            'payin.api_secret' => 'sk_test',
            'payments.gateway_mode' => 'live',
        ]);

        $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-RETRY-'.uniqid(),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Retry',
            'last_name' => 'Push',
            'phone' => '255715222132',
            'country_code' => 'TZ',
        ]);

        $payment = CustomerPayment::create([
            'reference' => 'VAL-CS-RETRY-001',
            'customer_id' => $customer->id,
            'payment_type' => 'valuation_fee',
            'payment_method' => 'mobile_money',
            'amount' => 1100,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'mobile_number' => '255715222132',
        ]);

        return [$user, $customer, $payment];
    }

    public function test_two_collect_attempts_send_distinct_idempotency_keys(): void
    {
        [, , $payment] = $this->awaitingPayment();
        $keys = [];

        Http::fake(function ($request) use (&$keys) {
            $keys[] = $request->header('X-Idempotency-Key');

            return Http::response([
                'success' => true,
                'request_ref' => 'PAY'.uniqid(),
                'status' => 'processing',
                'operator' => 'Tigo Pesa',
                'message' => 'Collection request sent to operator.',
            ]);
        });

        $payments = app(CustomerPaymentService::class);
        $first = $payments->initiateCollection($payment, '255715222132');
        $this->assertSame('processing', $first->status);
        $this->assertSame(1, (int) data_get($first->provider_meta, 'collect_attempt'));

        $reset = $payments->returnToPaymentGate($first);
        $second = $payments->initiateCollection($reset, '255715222132');

        $this->assertCount(2, $keys);
        $this->assertNotSame($keys[0], $keys[1]);
        $this->assertNotSame($payment->reference, $keys[0]);
        $this->assertSame(2, (int) data_get($second->provider_meta, 'collect_attempt'));
        $this->assertNotSame(
            data_get($first->provider_meta, 'idempotency_key'),
            data_get($second->fresh()->provider_meta, 'idempotency_key')
        );
    }

    public function test_pay_now_sends_chosen_wallet_to_payin(): void
    {
        [$user, , $payment] = $this->awaitingPayment();
        $operators = [];

        Http::fake(function ($request) use (&$operators) {
            $operators[] = $request->data()['operator'] ?? null;

            return Http::response([
                'success' => true,
                'request_ref' => 'PAY-MPESA-1',
                'status' => 'processing',
                'operator' => 'M-Pesa',
                'message' => 'Collection request sent to operator.',
            ]);
        });

        $this->actingAs($user)
            ->post(route('site.borrower.payments.pay', $payment), [
                'payment_method' => 'mobile_money',
                'mobile_number' => '255715222132',
                'operator' => 'mpesa',
            ])
            ->assertRedirect(route('site.borrower.payments.show', $payment));

        $this->assertSame(['mpesa'], $operators);
        $this->assertSame('mpesa', data_get($payment->fresh()->provider_meta, 'requested_operator'));
    }

    public function test_waiting_page_tells_borrower_to_check_mixx(): void
    {
        [$user, , $payment] = $this->awaitingPayment();
        $payment->update([
            'status' => 'processing',
            'provider' => 'payin',
            'provider_ref' => 'PAY-MIXX-1',
            'provider_meta' => [
                'operator' => 'Tigo Pesa',
                'phone' => '255715222132',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertSee(__('borrower.payment_waiting.wallet_mixx'), false)
            ->assertSee(__('borrower.payment_waiting.wait_estimate'), false)
            ->assertSee('Mixx by Yas', false);
    }

    public function test_payment_gate_shows_wallet_picker(): void
    {
        [$user, , $payment] = $this->awaitingPayment();

        $this->actingAs($user)
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertSee(__('borrower.payment_waiting.wallet_label'), false)
            ->assertSee(__('borrower.payment_waiting.wallet_auto'), false)
            ->assertSee(__('borrower.payment_waiting.wallet_mixx'), false)
            ->assertSee(__('borrower.payment_waiting.wallet_mpesa'), false);
    }

    public function test_fresh_idempotency_keys_are_unique_per_call(): void
    {
        $payIn = app(PayInService::class);
        $a = $payIn->freshIdempotencyKey('VAL-CS-6A86E01B54F11');
        $b = $payIn->freshIdempotencyKey('VAL-CS-6A86E01B54F11');

        $this->assertNotSame($a, $b);
        $this->assertStringStartsWith('VAL-CS-6A86E01B54F11-', $a);
    }
}
