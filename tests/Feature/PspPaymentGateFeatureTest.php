<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PspPaymentGateFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_awaiting_payment_gate_shows_mobile_money_and_bank_transfer(): void
    {
        $user = User::factory()->create([
            'role' => 'borrower',
            'pin_hash' => bcrypt('1234'),
        ]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-PSP-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Gate',
            'last_name' => 'Tester',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
        ]);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'insurance_premium',
            'payment_method' => 'mobile_money',
            'amount' => 35000,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'reference' => 'INS-GATE-TEST-001',
            'mobile_number' => $customer->phone,
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertSee(__('borrower.payments_page.create.mobile_money'), false)
            ->assertSee(__('borrower.payments_page.create.bank_transfer'), false)
            ->assertSee('INS-GATE-TEST-001', false)
            ->assertSee(__('borrower.membership.pay_now'), false);
    }

    public function test_payment_gate_defaults_to_account_phone_not_payment_phone(): void
    {
        $user = User::factory()->create([
            'role' => 'borrower',
            'pin_hash' => bcrypt('1234'),
        ]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-PSP-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Gate',
            'last_name' => 'Phone',
            'phone' => '255722111222',
            'country_code' => 'TZ',
        ]);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'insurance_premium',
            'payment_method' => 'mobile_money',
            'amount' => 35000,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'reference' => 'INS-GATE-PHONE-001',
            'mobile_number' => '255715222132',
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertSee('722111222', false)
            ->assertDontSee('715222132', false);
    }
}