<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ReferralWallet;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create(array_merge([
            'user_id'         => $user->id,
            'customer_number' => 'C-TEST'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Test',
            'last_name'       => 'Member',
            'phone'           => '+255700'.random_int(100000, 999999),
        ], $overrides));
    }

    public function test_quote_applies_discount_and_wallet_cap(): void
    {
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-REF001']);
        $referred = $this->makeCustomer(['referred_by_customer_id' => $referrer->id]);
        ReferralWallet::create(['customer_id' => $referred->id, 'balance' => 5000]);

        $service = app(ReferralService::class);
        $quote = $service->quoteFee($referred, 10000, true, 'registration_fee');

        $this->assertSame(1000.0, $quote['discount']);
        $this->assertSame(9000.0, $quote['after_discount']);
        $this->assertSame(4500.0, $quote['wallet_applied']);
        $this->assertSame(4500.0, $quote['cash_due']);
        $this->assertSame(1000.0, $quote['commission']);
    }

    public function test_settle_fee_credits_referrer_and_debits_wallet(): void
    {
        $referrer = $this->makeCustomer(['referral_code' => 'KPF-REF002']);
        $referred = $this->makeCustomer(['referred_by_customer_id' => $referrer->id]);
        ReferralWallet::create(['customer_id' => $referred->id, 'balance' => 2000]);

        $service = app(ReferralService::class);
        $service->settleFee($referred, 10000, true, 'registration_fee');

        $this->assertSame(0.0, (float) $service->wallet($referred)->fresh()->balance);
        $this->assertSame(1000.0, (float) $service->wallet($referrer)->fresh()->balance);
    }
}
