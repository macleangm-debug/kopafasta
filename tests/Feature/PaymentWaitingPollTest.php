<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\User;
use App\Services\PayInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PaymentWaitingPollTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Customer} */
    private function borrower(): array
    {
        $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-WAIT-'.uniqid(),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Wait',
            'last_name' => 'Poll',
            'phone' => '255712345678',
        ]);

        return [$user, $customer];
    }

    public function test_processing_payment_status_endpoint_returns_waiting(): void
    {
        [$user, $customer] = $this->borrower();

        $payment = CustomerPayment::create([
            'reference' => 'PAY-WAIT1',
            'customer_id' => $customer->id,
            'payment_type' => 'registration_fee',
            'payment_method' => 'mobile_money',
            'amount' => 5000,
            'currency' => 'TZS',
            'status' => 'processing',
            'provider' => 'payin',
            'provider_ref' => 'PAYREF123',
            'mobile_number' => '255712345678',
        ]);

        $payIn = Mockery::mock(PayInService::class);
        $payIn->shouldReceive('status')->once()->with('PAYREF123')->andReturn([
            'ok' => true,
            'request_ref' => 'PAYREF123',
            'status' => 'processing',
            'message' => 'Still processing',
            'raw' => [],
        ]);
        $this->app->instance(PayInService::class, $payIn);

        $this->actingAs($user)
            ->getJson(route('site.borrower.payments.status', $payment))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'state' => 'waiting',
                'status' => 'processing',
            ]);
    }

    public function test_status_poll_marks_payment_verified_when_payin_completed(): void
    {
        [$user, $customer] = $this->borrower();

        $payment = CustomerPayment::create([
            'reference' => 'PAY-WAIT2',
            'customer_id' => $customer->id,
            'payment_type' => 'registration_fee',
            'payment_method' => 'mobile_money',
            'amount' => 2500,
            'currency' => 'TZS',
            'status' => 'processing',
            'provider' => 'payin',
            'provider_ref' => 'PAYREF999',
            'mobile_number' => '255700000000',
        ]);

        $payIn = Mockery::mock(PayInService::class);
        $payIn->shouldReceive('status')->once()->with('PAYREF999')->andReturn([
            'ok' => true,
            'request_ref' => 'PAYREF999',
            'status' => 'completed',
            'message' => 'Done',
            'raw' => [],
        ]);
        $this->app->instance(PayInService::class, $payIn);

        $this->actingAs($user)
            ->getJson(route('site.borrower.payments.status', $payment))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'state' => 'paid',
            ]);

        $this->assertSame('verified', $payment->fresh()->status);
    }

    public function test_waiting_page_renders_for_processing_payin_payment(): void
    {
        [$user, $customer] = $this->borrower();

        $payment = CustomerPayment::create([
            'reference' => 'PAY-WAIT3',
            'customer_id' => $customer->id,
            'payment_type' => 'registration_fee',
            'payment_method' => 'mobile_money',
            'amount' => 5000,
            'currency' => 'TZS',
            'status' => 'processing',
            'provider' => 'payin',
            'provider_ref' => 'PAYREF777',
            'mobile_number' => '255711111111',
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertSee(__('borrower.payment_waiting.title'), false)
            ->assertSee(__('borrower.payment_waiting.for'), false)
            ->assertSee(__('borrower.payment_types.registration_fee'), false)
            ->assertSee('255711111111')
            ->assertDontSee(__('borrower.payment_waiting.prompt'), false)
            ->assertDontSee(__('borrower.payment_waiting.step_ussd'), false)
            ->assertSee(__('borrower.payment_waiting.wait_estimate'), false)
            ->assertSee(__('borrower.payment_waiting.no_prompt'), false)
            ->assertSee(__('borrower.payment_waiting.help_title'), false)
            ->assertSee(__('borrower.payment_waiting.try_again'), false)
            ->assertSee(__('borrower.payment_waiting.change_phone'), false)
            ->assertDontSee(__('borrower.payment_waiting.keep_waiting'), false)
            ->assertSee(route('site.borrower.payments.gate', $payment), false);
    }

    public function test_return_to_gate_resets_processing_payment_to_psp_gate(): void
    {
        [$user, $customer] = $this->borrower();

        $payment = CustomerPayment::create([
            'reference' => 'PAY-WAIT-GATE',
            'customer_id' => $customer->id,
            'payment_type' => 'registration_fee',
            'payment_method' => 'mobile_money',
            'amount' => 5000,
            'currency' => 'TZS',
            'status' => 'processing',
            'provider' => 'payin',
            'provider_ref' => 'PAYREF-GATE',
            'mobile_number' => '255711111111',
        ]);

        $this->actingAs($user)
            ->post(route('site.borrower.payments.gate', $payment))
            ->assertRedirect(route('site.borrower.payments.show', $payment));

        $payment->refresh();
        $this->assertSame('awaiting_payment', $payment->status);
        $this->assertNull($payment->provider_ref);

        $this->actingAs($user)
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertSee(__('borrower.payments_page.create.mobile_money'), false)
            ->assertSee(__('borrower.payments_page.create.bank_transfer'), false)
            ->assertSee(__('borrower.membership.pay_now'), false);
    }

    public function test_waiting_page_shows_swahili_type_label_in_sw_locale(): void
    {
        app()->setLocale('sw');
        [$user, $customer] = $this->borrower();

        $payment = CustomerPayment::create([
            'reference' => 'PAY-WAIT4',
            'customer_id' => $customer->id,
            'payment_type' => 'registration_fee',
            'payment_method' => 'mobile_money',
            'amount' => 5000,
            'currency' => 'TZS',
            'status' => 'processing',
            'provider' => 'payin',
            'provider_ref' => 'PAYREF888',
            'mobile_number' => '255711111111',
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertSee('Ada ya Uanachama', false)
            ->assertDontSee('Membership Fee', false);
    }
}
