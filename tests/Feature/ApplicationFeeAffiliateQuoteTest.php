<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ApplicationFeePaymentService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationFeeAffiliateQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create(array_merge([
            'user_id'         => $user->id,
            'customer_number' => 'C-AFF'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Affiliate',
            'last_name'       => 'Borrower',
            'phone'           => '+255700'.random_int(100000, 999999),
            'membership_issued_at'  => now(),
            'membership_expires_at' => now()->addYear(),
        ], $overrides));
    }

    private function makeProduct(array $overrides = []): LoanProduct
    {
        return LoanProduct::create(array_merge([
            'code'                   => 'PL'.random_int(100, 999),
            'name'                   => 'Personal Loan',
            'category'               => 'personal',
            'is_active'              => true,
            'interest_rate'          => 0.03,
            'min_amount'             => 100_000,
            'max_amount'             => 5_000_000,
            'tenure_min_months'      => 1,
            'tenure_max_months'      => 12,
            'application_fee_amount' => 10_000,
        ], $overrides));
    }

    private function makeAffiliate(array $overrides = []): Vendor
    {
        return Vendor::create(array_merge([
            'vendor_number'                  => 'AFF-FEE-'.random_int(100, 999),
            'name'                           => 'Fee Affiliate',
            'category'                       => 'affiliate',
            'status'                         => 'active',
            'affiliate_code'                 => 'AFFFEE'.random_int(10, 99),
            'application_discount_percent'   => 10,
            'registration_discount_percent'  => 10,
        ], $overrides));
    }

    public function test_quote_applies_affiliate_code_for_registered_member(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $affiliate = $this->makeAffiliate(['affiliate_code' => 'AFFAPP10']);

        $quote = app(ApplicationFeePaymentService::class)->quote(
            $customer,
            $product,
            false,
            null,
            null,
            'AFFAPP10',
        );

        $this->assertTrue($quote['has_affiliate'] ?? false);
        $this->assertSame(1000.0, (float) $quote['affiliate_discount']);
        $this->assertSame(9000.0, (float) $quote['cash_due']);
        $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);
    }

    public function test_quote_endpoint_accepts_affiliate_code_query(): void
    {
        $customer = $this->makeCustomer();
        app(PinService::class)->setPin($customer->user, '1234');
        $product = $this->makeProduct();
        $this->makeAffiliate(['affiliate_code' => 'AFFQRY20', 'application_discount_percent' => 20]);

        $this->actingAs($customer->user)
            ->getJson(route('site.borrower.apply.application-fee.quote', [
                'loan_product_id' => $product->id,
                'promo_code'      => 'AFFQRY20',
                'affiliate_code'  => 'AFFQRY20',
            ]))
            ->assertOk()
            ->assertJsonPath('quote.has_affiliate', true)
            ->assertJsonPath('quote.affiliate_discount', 2000)
            ->assertJsonPath('quote.cash_due', 8000);
    }

    public function test_resolve_promo_or_affiliate_prefers_affiliate_match(): void
    {
        $this->makeAffiliate(['affiliate_code' => 'AFFONLY1']);
        $fees = app(ApplicationFeePaymentService::class);

        [$promo, $affiliate] = $fees->resolvePromoOrAffiliate('AFFONLY1');

        $this->assertNull($promo);
        $this->assertSame('AFFONLY1', $affiliate);

        [$promo2, $affiliate2] = $fees->resolvePromoOrAffiliate('NOTACODE');

        $this->assertSame('NOTACODE', $promo2);
        $this->assertNull($affiliate2);
    }
}
