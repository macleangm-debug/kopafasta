<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateService;
use App\Services\AffiliateSettingsService;
use App\Services\AffiliateTermsService;
use Illuminate\Http\Request;
use App\Services\CardVerificationService;
use App\Services\PartnerCodeService;
use App\Services\PaymentGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateCommercialWiringPassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_member_is_not_forced_through_registration(): void
    {
        $affiliate = $this->affiliate(['affiliate_code' => 'WIRE01', 'partner_number' => 'PT-AF-TZ-51SC']);
        $customer = $this->customer();
        $this->assertTrue(app(\App\Services\AffiliateEligibilityService::class)->canAttributeNewReferral($affiliate));

        $this->actingAs($customer->user)
            ->get('/aff/WIRE01')
            ->assertRedirect(route('site.borrower.dashboard'));

        $this->assertContains(session('affiliate_referral_outcome'), ['attached', 'already']);
        $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);
    }

    public function test_same_affiliate_is_not_duplicated(): void
    {
        $affiliate = $this->affiliate(['affiliate_code' => 'WIRE02']);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id]);

        $outcome = app(AffiliateService::class)->connectMember($customer, $affiliate);

        $this->assertSame('already', $outcome);
        $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);
    }

    public function test_protected_attribution_is_not_overwritten(): void
    {
        $first = $this->affiliate(['affiliate_code' => 'WIREA1']);
        $second = $this->affiliate(['affiliate_code' => 'WIREA2']);
        $customer = $this->customer(['affiliate_vendor_id' => $first->id]);
        $details = is_array($customer->activity_details) ? $customer->activity_details : [];
        $details['affiliate_attribution'] = [
            'affiliate_id' => $first->id,
            'locked_at' => now()->toIso8601String(),
        ];
        $customer->update(['activity_details' => $details]);

        $outcome = app(AffiliateService::class)->connectMember($customer->fresh(), $second);

        $this->assertSame('protected', $outcome);
        $this->assertSame($first->id, $customer->fresh()->affiliate_vendor_id);
    }

    public function test_attribution_settings_are_read_from_hub(): void
    {
        $settings = app(AffiliateSettingsService::class);

        $this->assertSame(30, $settings->attributionWindowDays());
        $this->assertSame('application_created', $settings->attributionLockAt());
        $this->assertFalse($settings->allowReplacementBeforeLock());
        $this->assertFalse($settings->allowOverrideAfterLock());
        $this->assertFalse($settings->existingCustomerReferral());
        $this->assertSame(14, $settings->promoOldCodeGraceDays());
        $this->assertSame('first_valid', $settings->attributionModel());
    }

    public function test_referral_token_stays_when_promo_changes(): void
    {
        $affiliate = $this->affiliate(['affiliate_code' => 'UATSTD01', 'partner_number' => 'PT-AF-TZ-51SC']);
        $service = app(AffiliateService::class);
        $token = $service->ensureReferralToken($affiliate);

        $this->assertNotSame('UATSTD01', $token);
        $this->assertNotSame('PT-AF-TZ-51SC', $token);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $token);
        $this->assertStringContainsString('/aff/'.$token, $service->affiliateLink($affiliate));

        Setting::set('affiliates.promo_code', [
            'affiliate_can_edit' => true,
            'change_cooldown_days' => 0,
            'old_code_grace_days' => 14,
        ]);
        $service->updateCode($affiliate->fresh(), 'MAPROSO2');

        $fresh = $affiliate->fresh();
        $this->assertSame('MAPROSO2', $fresh->affiliate_code);
        $this->assertSame('PT-AF-TZ-51SC', $fresh->partner_number);
        $this->assertSame($token, $service->ensureReferralToken($fresh));
        $this->assertSame($affiliate->id, $service->resolveByPublicCode('UATSTD01')?->id);
        $this->assertSame($affiliate->id, $service->resolveByPublicCode('MAPROSO2')?->id);
        $this->assertSame($affiliate->id, $service->resolveByPublicCode($token)?->id);
        $this->assertNotSame($affiliate->name, $fresh->affiliate_code);
    }

    public function test_partner_code_uses_compact_suffix_not_a_name(): void
    {
        $code = app(PartnerCodeService::class)->generate('affiliate');

        $this->assertMatchesRegularExpression('/^PT-AF-TZ-[A-Z0-9]{4}$/', $code);
        $this->assertStringNotContainsString('CLOSURE', $code);
    }

    public function test_registration_hero_is_compact_and_hides_benefit_in_body(): void
    {
        $this->affiliate([
            'name' => 'Kitonga Style',
            'affiliate_code' => 'MAPROSO',
            'partner_number' => 'PT-AF-TZ-51SC',
            'application_discount_percent' => 10,
        ]);

        $html = $this->withSession(['locale' => 'sw'])
            ->get(route('site.register.borrower', ['aff' => 'MAPROSO']))
            ->assertOk()
            ->assertSee('Kitonga Style', false)
            ->assertSee('PT-AF-TZ-51SC', false)
            ->assertSee(__('borrower.register.affiliate_invited_by', [], 'sw'), false)
            ->assertSee(__('borrower.register.affiliate_benefits_title', [], 'sw'), false)
            ->assertDontSee('Msambazaji', false)
            ->getContent();

        $this->assertStringContainsString(brand_name(), $html);
        $this->assertStringNotContainsString(__('borrower.register.affiliate_invited', ['brand' => brand_name()], 'sw'), $html);
        $this->assertStringNotContainsString(__('site.affiliate_portal.link_welcome', [], 'sw'), $html);
    }

    public function test_affiliate_link_does_not_flash_duplicate_welcome(): void
    {
        $this->affiliate([
            'name' => 'UAT Standard Affiliate',
            'affiliate_code' => 'UATSTD01',
            'partner_number' => 'PT-AF-TZ-51SC',
        ]);

        $this->withSession(['locale' => 'sw'])
            ->get('/aff/UATSTD01')
            ->assertRedirect();

        $this->assertNull(session('status'));
    }

    public function test_manual_promo_does_not_expose_customer_exception(): void
    {
        $affiliate = $this->affiliate(['affiliate_code' => 'UATSTD01']);
        $customer = $this->customer();
        $payment = $this->feePayment($customer);

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.payments.adjust', $payment), [
                'promo_code' => 'UATSTD01',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('promo_valid', true)
            ->assertJsonPath('quote.cash_due', 9000);

        $this->assertStringNotContainsString('App\\Models\\Customer', json_encode(
            $this->actingAs($customer->user)
                ->postJson(route('site.borrower.payments.adjust', $payment->fresh()), [
                    'promo_code' => 'UATSTD01',
                ])
                ->json()
        ));

        $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);
    }

    public function test_attributed_payment_hides_promo_entry_and_keeps_net(): void
    {
        $affiliate = $this->affiliate(['affiliate_code' => 'AUTO10']);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id]);
        $payment = $this->feePayment($customer);

        $html = $this->actingAs($customer->user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.benefit_applied', [], 'en'), false)
            ->assertSee(__('borrower.payments_page.show.application_fee_disclaimer', [], 'en'), false)
            ->getContent();

        $this->assertStringContainsString(__('borrower.payment_types.application_fee', [], 'en'), $html);
        $quote = app(PaymentGateService::class)->quote($customer, 10000, 'application_fee');
        $this->assertSame(9000.0, (float) $quote['cash_due']);
        $this->assertTrue($quote['has_affiliate']);
        $this->assertTrue($quote['affiliate_auto_applied']);
    }

    public function test_zero_benefit_keeps_attribution_and_amount(): void
    {
        $affiliate = $this->affiliate([
            'affiliate_code' => 'ZERO01',
            'application_discount_percent' => 0,
        ]);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id]);
        $quote = app(PaymentGateService::class)->quote($customer, 10000, 'application_fee');

        $this->assertTrue($quote['has_affiliate']);
        $this->assertSame(0.0, (float) $quote['affiliate_discount']);
        $this->assertSame(10000.0, (float) $quote['cash_due']);
    }

    public function test_share_message_is_settings_driven_and_has_disclaimer(): void
    {
        $affiliate = $this->affiliate(['affiliate_code' => 'SHARE01']);
        $message = app(AffiliateService::class)->shareInvitation($affiliate, 'en');

        $this->assertStringContainsString('does not guarantee loan approval', $message);
        $this->assertStringContainsString('SHARE01', $message);
        $this->assertStringContainsString($affiliate->name, $message);
        $this->assertStringContainsString('/aff/', $message);
    }

    public function test_verification_uses_canonical_partner_number(): void
    {
        $affiliate = $this->affiliate(['partner_number' => 'PT-AF-TZ-51SC', 'affiliate_code' => 'UATSTD01']);
        $cards = app(CardVerificationService::class);

        $this->assertSame('PT-AF-TZ-51SC', $cards->composeId('affiliate', 'PT-AF-TZ-51SC'));
        $result = $cards->lookup('affiliate', 'PT-AF-TZ-51SC');
        $this->assertTrue($result['found']);
        $this->assertTrue($result['verified']);
        $this->assertSame('PT-AF-TZ-51SC', $result['id_display']);
        $this->assertSame($affiliate->id, $result['partner']?->id);
        $this->assertNotSame('UATSTD01', $result['id_display']);
    }

    public function test_plus_payment_uses_plus_benefit_not_application_fee_label(): void
    {
        $affiliate = $this->affiliate([
            'affiliate_code' => 'UATSTD01',
            'partner_number' => 'PT-AF-TZ-51SC',
        ]);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id]);
        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'kopafasta_plus',
            'payment_method' => 'mobile_money',
            'status' => 'awaiting_payment',
            'amount' => 1000,
            'currency' => 'TZS',
            'reference' => 'PAY-PLUS148',
            'provider_meta' => [
                'pricing' => ['gross' => 1000],
            ],
        ]);

        $html = $this->actingAs($customer->user)
            ->withSession(['locale' => 'sw'])
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertSee('Kopafasta Plus', false)
            ->assertSee(__('site.affiliate_portal.benefit_applied', [], 'sw'), false)
            ->assertDontSee(__('borrower.payments_page.show.obligation_line', [], 'sw'), false)
            ->assertDontSee(__('borrower.membership.apply_promo_link', [], 'sw'), false)
            ->getContent();

        $this->assertStringNotContainsString(__('borrower.payment_types.application_fee', [], 'sw'), $html);

        $quote = app(PaymentGateService::class)->quote($customer, 1000, 'kopafasta_plus');
        $this->assertTrue($quote['has_affiliate']);
        $this->assertSame(1000.0, (float) $quote['base']);
        $this->assertSame(100.0, (float) $quote['affiliate_discount']);
        $this->assertSame(900.0, (float) $quote['cash_due']);
        $this->assertSame('Kopafasta Plus', $quote['lines'][0]['label'] ?? null);

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.payments.adjust', $payment), [
                'promo_code' => 'UATSTD01',
            ])
            ->assertOk()
            ->assertJsonPath('quote.cash_due', 900);

        $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);
    }

    public function test_manual_promo_is_fallback_when_unattributed(): void
    {
        $affiliate = $this->affiliate(['affiliate_code' => 'UATSTD01']);
        $customer = $this->customer();
        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'kopafasta_plus',
            'payment_method' => 'mobile_money',
            'status' => 'awaiting_payment',
            'amount' => 1000,
            'currency' => 'TZS',
            'reference' => 'PAY-PLUSFB',
            'provider_meta' => ['pricing' => ['gross' => 1000]],
        ]);

        $html = $this->actingAs($customer->user)
            ->withSession(['locale' => 'sw'])
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('borrower.membership.apply_promo_link', [], 'sw'), $html);

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.payments.adjust', $payment), [
                'promo_code' => 'UATSTD01',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('promo_valid', true)
            ->assertJsonPath('quote.cash_due', 900)
            ->assertJsonPath('quote.lines.0.label', 'Kopafasta Plus');

        $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);
    }

    public function test_guest_redirect_preserves_referral_and_opens_register(): void
    {
        $affiliate = $this->affiliate(['affiliate_code' => 'GUEST1']);

        $response = $this->get('/aff/GUEST1');
        $token = app(AffiliateService::class)->ensureReferralToken($affiliate->fresh());

        $response->assertRedirect(route('site.register.borrower', ['aff' => $token]));
        $this->assertSame($affiliate->id, session('affiliate_claim')['affiliate_id'] ?? null);
        $this->assertNull(session('status'));
    }

    private function affiliate(array $overrides = []): Vendor
    {
        $affiliate = Vendor::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'vendor'])->id,
            'vendor_number' => 'AFF-CW-'.random_int(100, 999),
            'name' => 'Closure Wiring',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '2557123'.random_int(10000, 99999),
            'affiliate_code' => 'CW'.random_int(1000, 9999),
            'affiliate_kyc_status' => 'verified',
            'affiliate_lifecycle_status' => 'active',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
            'application_discount_percent' => 10,
        ], $overrides));
        app(AffiliateTermsService::class)->accept($affiliate, Request::create('/terms', 'POST'));

        return $affiliate->fresh();
    }

    private function customer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'C-CW'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Wired',
            'last_name' => 'Borrower',
            'phone' => '+255700'.random_int(100000, 999999),
            'country_code' => 'TZ',
        ], $overrides));
    }

    private function feePayment(Customer $customer): CustomerPayment
    {
        $product = LoanProduct::create([
            'code' => 'CW'.random_int(100, 999),
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

        return CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'status' => 'awaiting_payment',
            'amount' => 10000,
            'currency' => 'TZS',
            'reference' => 'PAY-CW'.random_int(1000, 9999),
            'provider_meta' => [
                'pricing' => ['gross' => 10000],
                'apply_context' => ['gross_amount' => 10000, 'loan_product_id' => $product->id],
            ],
        ]);
    }
}
