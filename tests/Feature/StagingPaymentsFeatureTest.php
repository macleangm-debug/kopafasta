<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanProduct;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PaymentGateService;
use App\Services\Staging\StagingPaymentsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StagingPaymentsFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function asStaging(): void
    {
        config([
            'staging_payments.testing_enabled' => true,
            'staging_payments.use_price_overrides' => true,
        ]);
    }

    private function asProduction(): void
    {
        config([
            'app.env' => 'production',
            'staging_payments.testing_enabled' => false,
        ]);
        app()->instance('env', 'production');
    }

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-STG-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Staging',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
            'loyalty_points' => 500,
        ]);
    }

    private function product(int $fee = 10_000): LoanProduct
    {
        return LoanProduct::create([
            'code' => 'IL-STG-'.random_int(100, 999),
            'name' => 'Individual Loan',
            'category' => 'individual',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'application_fee_amount' => $fee,
        ]);
    }

    public function test_production_ignores_staging_price_overrides(): void
    {
        $this->asProduction();
        $product = $this->product(10_000);

        $this->assertFalse(app(StagingPaymentsService::class)->isEnabled());
        $this->assertSame(10000, quoted_application_fee(null, $product));
    }

    public function test_staging_replaces_canonical_application_fee_without_writing_it(): void
    {
        $this->asStaging();
        $product = $this->product(10_000);

        $this->assertSame(500, quoted_application_fee(null, $product));
        $this->assertSame(10000, (int) $product->fresh()->application_fee_amount);
    }

    public function test_kitonga_arithmetic_runs_on_the_staging_effective_amount(): void
    {
        $this->asStaging();
        $customer = $this->borrower();
        Vendor::create([
            'vendor_number' => 'AFF-KIT-'.random_int(10, 99),
            'name' => 'Kitonga Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'affiliate_code' => 'KITONGA',
            'affiliate_kyc_status' => 'verified',
            'affiliate_lifecycle_status' => 'active',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
            'application_discount_percent' => 10,
        ]);

        $quote = app(PaymentGateService::class)->quote($customer, 10_000, 'application_fee', false, 'KITONGA');
        $this->assertEquals(500.0, (float) $quote['base']);
        $this->assertEquals(10000.0, (float) $quote['canonical_base']);
        $this->assertEquals(50.0, (float) $quote['affiliate_discount']);
        $this->assertEquals(450.0, (float) $quote['cash_due']);
        $this->assertContains('base', collect($quote['lines'])->pluck('key')->all());
        $this->assertContains('affiliate', collect($quote['lines'])->pluck('key')->all());
        $this->assertContains('payable', collect($quote['lines'])->pluck('key')->all());
    }

    public function test_simulator_success_verifies_through_the_real_payment_service(): void
    {
        $this->asStaging();
        $customer = $this->borrower();
        $product = $this->product(10_000);
        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 500,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'reference' => 'PAY-STG-'.random_int(1000, 9999),
            'provider_meta' => ['awaiting_collection' => true],
        ]);

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.payments.pay', $payment), [
                'payment_method' => 'mobile_money',
                'mobile_number' => $customer->phone,
            ])
            ->assertOk()
            ->assertJsonPath('state', 'waiting');

        $payment->refresh();
        $this->assertSame('processing', $payment->status);
        $this->assertSame(StagingPaymentsService::PROVIDER, $payment->provider);
        $this->assertNotEmpty($payment->provider_ref);

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.payments.simulate', $payment), [
                'outcome' => 'success',
            ])
            ->assertOk()
            ->assertJsonPath('state', 'paid');

        $this->assertTrue($payment->fresh()->isVerified());
    }

    public function test_simulator_failure_does_not_mark_the_obligation_paid(): void
    {
        $this->asStaging();
        $customer = $this->borrower();
        $product = $this->product(10_000);
        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 500,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'reference' => 'PAY-STG-F'.random_int(1000, 9999),
            'provider_meta' => ['awaiting_collection' => true],
        ]);

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.payments.pay', $payment), [
                'payment_method' => 'mobile_money',
                'mobile_number' => $customer->phone,
            ])
            ->assertOk();

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.payments.simulate', $payment), [
                'outcome' => 'failed',
            ])
            ->assertOk();

        $fresh = $payment->fresh();
        $this->assertFalse($fresh->isVerified());
        $this->assertSame('awaiting_payment', $fresh->status);
    }

    public function test_simulator_is_impossible_in_production(): void
    {
        $this->asProduction();
        $this->assertFalse(app(StagingPaymentsService::class)->isEnabled());
        $this->assertFalse(app(StagingPaymentsService::class)->isSimulator());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\Staging\StagingPaymentSimulator::class)->applyOutcome(new CustomerPayment, 'success');
    }

    public function test_staging_payments_admin_section_is_hidden_outside_staging(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.system'))
            ->assertOk()
            ->assertDontSee('Staging Payments', false);
    }

    public function test_payin_staging_cannot_use_the_production_environment_flag(): void
    {
        config(['app.env' => 'staging']);
        app()->instance('env', 'staging');
        \App\Models\Setting::set('payin.environment', 'production');

        $this->assertSame('sandbox', app(\App\Services\PayInService::class)->settings()['environment']);
    }
}
