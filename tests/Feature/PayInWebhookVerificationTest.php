<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Setting;
use App\Models\User;
use App\Services\PayInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PayInWebhookVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function processingPayment(string $ref = 'REQ-FAILED-1'): CustomerPayment
    {
        $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-WH-'.uniqid(),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Web',
            'last_name' => 'Hook',
            'phone' => '255700111222',
        ]);

        return CustomerPayment::create([
            'reference' => 'PAY-8NL0EM',
            'customer_id' => $customer->id,
            'payment_type' => 'registration_fee',
            'payment_method' => 'mobile_money',
            'amount' => 2000,
            'currency' => 'TZS',
            'status' => 'processing',
            'provider' => 'payin',
            'provider_ref' => $ref,
            'mobile_number' => '255700111222',
        ]);
    }

    private function fakeSignature(): void
    {
        Setting::setMany([
            'payin.enabled' => true,
            'payin.api_key' => 'pk',
            'payin.api_secret' => 'sk',
            'payin.webhook_secret' => 'whsec_test',
        ]);

        $payIn = Mockery::mock(PayInService::class)->makePartial();
        $payIn->shouldReceive('verifyWebhookSignature')->andReturn(true);
        $this->app->instance(PayInService::class, $payIn);
    }

    public function test_webhook_rejects_when_event_completed_but_status_failed(): void
    {
        $this->fakeSignature();
        $payment = $this->processingPayment();

        $this->postJson(route('webhooks.payin'), [
            'event' => 'payin.completed',
            'request_ref' => 'REQ-FAILED-1',
            'status' => 'failed',
            'external_ref' => 'PAY-8NL0EM',
        ])->assertOk();

        $fresh = $payment->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertNull($fresh->verified_at);
    }

    public function test_webhook_verifies_when_status_completed(): void
    {
        $this->fakeSignature();
        $payment = $this->processingPayment('REQ-OK-1');

        $this->postJson(route('webhooks.payin'), [
            'event' => 'payin.completed',
            'request_ref' => 'REQ-OK-1',
            'status' => 'completed',
            'external_ref' => 'PAY-8NL0EM',
        ])->assertOk();

        $this->assertSame('verified', $payment->fresh()->status);
    }
}
