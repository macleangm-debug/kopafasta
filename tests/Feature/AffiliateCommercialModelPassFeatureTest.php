<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\PartnerPayment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateAttributionService;
use App\Services\AffiliateService;
use App\Services\AffiliateTermsService;
use App\Services\CustomerPaymentService;
use App\Services\PaymentGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AffiliateCommercialModelPassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_promo_is_per_payment_and_does_not_create_a_relationship(): void
    {
        $first = $this->affiliate(['affiliate_code' => 'UATSTD01', 'name' => 'UAT Standard Affiliate']);
        $second = $this->affiliate(['affiliate_code' => 'UATSTD02', 'name' => 'UAT Rival Affiliate']);
        $customer = $this->customer();

        $firstQuote = app(PaymentGateService::class)->quote($customer, 1000, 'application_fee', false, 'UATSTD01');
        $this->assertSame('promo', $firstQuote['attribution_source']);
        $this->assertFalse($firstQuote['affiliate_auto_applied']);
        $this->assertSame(100.0, (float) $firstQuote['affiliate_discount']);
        $this->assertNull($customer->fresh()->affiliate_vendor_id);

        $payment = $this->plusPayment($customer, 'PAY-PROMO1');
        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.payments.adjust', $payment), ['promo_code' => 'UATSTD01'])
            ->assertOk()
            ->assertJsonPath('quote.cash_due', 900)
            ->assertJsonPath('quote.attribution_source', 'promo');

        $this->assertNull($customer->fresh()->affiliate_vendor_id);
        $this->assertSame($first->id, (int) data_get($payment->fresh()->provider_meta, 'pricing.affiliate_partner_id'));
        $this->assertSame('UATSTD01', data_get($payment->fresh()->provider_meta, 'pricing.promo_code_snapshot'));

        $next = $this->plusPayment($customer, 'PAY-PROMO2');
        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.payments.show', $next))
            ->assertOk()
            ->assertSee(__('borrower.membership.apply_promo_link'), false)
            ->assertDontSee(__('site.affiliate_portal.referred_by', ['name' => $first->name]), false)
            ->getContent();
        $this->assertStringContainsString(__('borrower.membership.apply_promo_link'), $html);

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.payments.adjust', $next), ['promo_code' => 'UATSTD02'])
            ->assertOk()
            ->assertJsonPath('quote.cash_due', 900)
            ->assertJsonPath('quote.attribution_source', 'promo')
            ->assertJsonPath('quote.affiliate_partner_id', $second->id);

        $this->assertNull($customer->fresh()->affiliate_vendor_id);
        $this->assertSame($second->id, (int) data_get($next->fresh()->provider_meta, 'pricing.affiliate_partner_id'));
    }

    public function test_referral_link_creates_a_settings_window_relationship(): void
    {
        $affiliate = $this->affiliate(['affiliate_code' => 'REFLINK1', 'name' => 'Link Affiliate']);
        $customer = $this->existingMember();

        $this->actingAs($customer->user)
            ->get('/aff/REFLINK1')
            ->assertRedirect(route('site.borrower.dashboard'));

        $customer = $customer->fresh();
        $this->assertSame($affiliate->id, (int) $customer->affiliate_vendor_id);
        $claim = app(AffiliateAttributionService::class)->customerClaim($customer);
        $this->assertContains($claim['source'] ?? null, ['link', 'login']);
        $this->assertNotEmpty($claim['attributed_at'] ?? null);
        $this->assertNotEmpty($claim['expires_at'] ?? null);
        $this->assertTrue(app(AffiliateAttributionService::class)->hasValidRelationship($customer));

        $quote = app(PaymentGateService::class)->quote($customer, 1000, 'kopafasta_plus');
        $this->assertTrue($quote['affiliate_auto_applied']);
        $this->assertSame('referral_link', $quote['attribution_source']);
        $this->assertSame('Link Affiliate', $quote['referred_by']);
        $this->assertSame(900.0, (float) $quote['cash_due']);
    }

    public function test_expired_relationship_stops_automatic_benefit(): void
    {
        $affiliate = $this->affiliate(['affiliate_code' => 'EXPIRED1']);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id]);
        $details = is_array($customer->activity_details) ? $customer->activity_details : [];
        $details['affiliate_attribution'] = [
            'affiliate_id' => $affiliate->id,
            'source' => 'link',
            'attributed_at' => now()->subDays(40)->toIso8601String(),
            'expires_at' => now()->subDay()->toIso8601String(),
            'window_days' => 30,
        ];
        $customer->update(['activity_details' => $details]);

        $this->assertFalse(app(AffiliateAttributionService::class)->hasValidRelationship($customer->fresh()));

        $quote = app(PaymentGateService::class)->quote($customer->fresh(), 1000, 'kopafasta_plus');
        $this->assertFalse($quote['affiliate_auto_applied']);
        $this->assertSame(1000.0, (float) $quote['cash_due']);

        $payment = $this->plusPayment($customer, 'PAY-EXPIRED');
        $this->actingAs($customer->user)
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertSee(__('borrower.membership.apply_promo_link'), false)
            ->assertDontSee(__('site.affiliate_portal.benefit_applied'), false);
    }

    public function test_promo_only_relationship_is_cleared_and_snapshot_stays_on_payment(): void
    {
        $affiliate = $this->affiliate(['affiliate_code' => 'UATSTD01', 'partner_number' => 'PT-AF-TZ-WON4']);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id, 'member_no' => 'KPF-TZ-WLN5']);
        $details = is_array($customer->activity_details) ? $customer->activity_details : [];
        $details['affiliate_attribution'] = [
            'affiliate_id' => $affiliate->id,
            'source' => 'promo',
            'code_used' => 'UATSTD01',
            'attributed_at' => now()->toIso8601String(),
        ];
        $customer->update(['activity_details' => $details]);

        $payment = $this->plusPayment($customer, 'PAY-D7TH1M');
        $payment->update([
            'status' => 'verified',
            'paid_at' => now(),
            'provider_meta' => [
                'pricing' => [
                    'gross' => 1000,
                    'net_payable' => 900,
                    'affiliate_partner_id' => $affiliate->id,
                    'attribution_source' => 'promo',
                    'promo_code_snapshot' => 'UATSTD01',
                    'benefit_amount' => 100,
                    'commission_amount' => 90,
                ],
            ],
        ]);

        $this->assertTrue(app(AffiliateAttributionService::class)->clearPromoOnlyRelationship($customer->fresh()));
        $this->assertNull($customer->fresh()->affiliate_vendor_id);
        $this->assertSame($affiliate->id, (int) data_get($payment->fresh()->provider_meta, 'pricing.affiliate_partner_id'));

        $plus = $this->plusPayment($customer->fresh(), 'PAY-PLUSNEXT');
        $this->actingAs($customer->user)
            ->get(route('site.borrower.payments.show', $plus))
            ->assertOk()
            ->assertSee(__('borrower.membership.apply_promo_link'), false)
            ->assertDontSee(__('site.affiliate_portal.referred_by', ['name' => $affiliate->name]), false);
    }

    public function test_verified_promo_payment_posts_complete_commission_to_available(): void
    {
        $affiliate = $this->affiliate(['affiliate_code' => 'UATSTD01']);
        $customer = $this->customer();
        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'status' => 'awaiting_payment',
            'amount' => 900,
            'currency' => 'TZS',
            'reference' => 'PAY-D7SNAP',
            'provider_meta' => [
                'pricing' => [
                    'gross' => 1000,
                    'net_payable' => 900,
                    'affiliate_partner_id' => $affiliate->id,
                    'attribution_source' => 'promo',
                    'promo_code_snapshot' => 'UATSTD01',
                    'commission_amount' => 90,
                ],
            ],
        ]);

        $verified = app(CustomerPaymentService::class)->verify($payment);
        $this->assertTrue($verified->isVerified());
        $this->assertNull($customer->fresh()->affiliate_vendor_id);

        $row = PartnerPayment::query()
            ->where('partner_id', $affiliate->id)
            ->where('source_type', 'affiliate_commission')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('approved', $row->status);
        $this->assertSame(90, (int) $row->amount);
        $this->assertSame('PAY-D7SNAP', $row->reference);
    }

    private function affiliate(array $overrides = []): Vendor
    {
        $affiliate = Vendor::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'vendor'])->id,
            'vendor_number' => 'AFF-CM-'.random_int(100, 999),
            'name' => 'Commercial Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '2557123'.random_int(10000, 99999),
            'affiliate_code' => 'CM'.random_int(1000, 9999),
            'affiliate_kyc_status' => 'verified',
            'affiliate_lifecycle_status' => 'active',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
            'application_discount_percent' => 10,
            'metadata' => ['plus_discount_percent' => 10],
        ], $overrides));
        app(AffiliateTermsService::class)->accept($affiliate, Request::create('/terms', 'POST'));

        return $affiliate->fresh();
    }

    private function customer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'C-CM'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Halima',
            'last_name' => 'Khamis',
            'phone' => '+255600'.random_int(100000, 999999),
            'country_code' => 'TZ',
        ], $overrides));
    }

    private function existingMember(): Customer
    {
        $customer = $this->customer();
        $product = LoanProduct::create([
            'code' => 'CM'.random_int(100, 999),
            'name' => 'Personal Loan',
            'category' => 'personal',
            'is_active' => true,
            'interest_rate' => 0.03,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'application_fee_amount' => 10_000,
        ]);
        LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-CM-'.random_int(1000, 9999),
            'requested_amount' => 1_000_000,
            'requested_tenure_months' => 12,
            'status' => 'submitted',
            'current_stage' => 'submitted',
        ]);

        return $customer->fresh();
    }

    private function plusPayment(Customer $customer, string $reference): CustomerPayment
    {
        return CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'kopafasta_plus',
            'payment_method' => 'mobile_money',
            'status' => 'awaiting_payment',
            'amount' => 1000,
            'currency' => 'TZS',
            'reference' => $reference,
            'provider_meta' => ['pricing' => ['gross' => 1000]],
        ]);
    }
}
