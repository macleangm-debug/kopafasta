<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\PartnerAgreementAcceptance;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateTermsService;
use App\Services\ApplicationFeePaymentService;
use App\Services\CardVerificationService;
use App\Services\PartnerProfileService;
use App\Services\PaymentGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AffiliateFinalClosurePassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_autosave_returns_canonical_completion(): void
    {
        $affiliate = $this->affiliate(['metadata' => [
            'residence' => ['region' => 'Dar es Salaam', 'district' => 'Ilala'],
        ]]);

        $payload = app(PartnerProfileService::class)->jsonSavedPayload($affiliate, 'residence', false);

        $this->assertTrue($payload['ok']);
        $this->assertArrayHasKey('completion', $payload);
        $this->assertFalse($payload['completion']['section_complete']);
        $this->assertContains('street', array_column($payload['completion']['gaps'], 'key'));
        $this->assertGreaterThan(0, $payload['completion']['remaining']);
    }

    public function test_canonical_partner_number_is_not_reprefixed(): void
    {
        $affiliate = $this->affiliate(['partner_number' => 'AFF-UAT-STD', 'vendor_number' => 'AFF-UAT-STD']);
        $cards = app(CardVerificationService::class);

        $this->assertSame('AFF-UAT-STD', $cards->composeId('affiliate', 'AFF-UAT-STD'));
        $result = $cards->lookup('affiliate', 'AFF-UAT-STD');
        $this->assertTrue($result['found']);
        $this->assertTrue($result['verified']);
        $this->assertSame('AFF-UAT-STD', $result['id_display']);
        $this->assertSame($affiliate->id, $result['partner']?->id);
    }

    public function test_active_partner_stays_active_when_profile_incomplete(): void
    {
        $affiliate = $this->affiliate(['partner_number' => 'AFF-OPEN-1', 'metadata' => []]);
        $result = app(CardVerificationService::class)->lookup('affiliate', 'AFF-OPEN-1');

        $this->assertTrue($result['found']);
        $this->assertTrue($result['verified']);
        $this->assertSame(__('site.card_verify.status.active'), $result['status_label']);
        $this->assertFalse(app(PartnerProfileService::class)->isComplete($affiliate));
    }

    public function test_tz_member_card_does_not_require_paid_membership(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-TZ-CARD',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Juma',
            'phone' => '255700111222',
            'country_code' => 'TZ',
            'member_no' => 'KPF-TZ-ASHA',
            'membership_issued_at' => null,
            'membership_expires_at' => null,
        ]);

        $this->assertFalse($customer->hasMembership());
        $this->assertTrue($customer->isMembershipActive());

        $result = app(CardVerificationService::class)->lookup('member', 'ASHA');
        $this->assertTrue($result['found']);
        $this->assertTrue($result['verified']);
        $this->assertSame(__('borrower.membership.badge_active'), $result['status_label']);
    }

    public function test_registration_shows_affiliate_inset_without_msambazaji(): void
    {
        $affiliate = $this->affiliate([
            'name' => 'Kitonga Style',
            'affiliate_code' => 'MAPROSO',
            'partner_number' => 'AFF-UAT-KIT',
            'application_discount_percent' => 10,
        ]);

        $html = $this->withSession(['locale' => 'sw', 'country' => 'TZ', 'affiliate_code' => 'MAPROSO'])
            ->get(route('site.register.borrower', ['aff' => 'MAPROSO']))
            ->assertOk()
            ->assertSee('Kitonga Style', false)
            ->assertSee('AFF-UAT-KIT', false)
            ->assertSee(__('borrower.register.affiliate_brought_by', [], 'sw'), false)
            ->assertDontSee('Msambazaji', false)
            ->getContent();

        $this->assertStringContainsString(brand_name(), $html);
        $this->assertSame($affiliate->affiliate_code, 'MAPROSO');
    }

    public function test_configured_benefit_changes_fee_and_zero_preserves_attribution(): void
    {
        $affiliate = $this->affiliate([
            'affiliate_code' => 'BENE10',
            'application_discount_percent' => 10,
        ]);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id]);
        $product = $this->product();

        $quote = app(ApplicationFeePaymentService::class)->quote($customer, $product);
        $this->assertTrue($quote['has_affiliate']);
        $this->assertSame(1000.0, (float) $quote['affiliate_discount']);
        $this->assertSame(9000.0, (float) $quote['cash_due']);
        $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);

        $affiliate->update(['application_discount_percent' => 0]);
        $zero = app(ApplicationFeePaymentService::class)->quote($customer->fresh(), $product);
        $this->assertTrue($zero['has_affiliate']);
        $this->assertSame(0.0, (float) $zero['affiliate_discount']);
        $this->assertSame(10000.0, (float) $zero['cash_due']);
        $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);
    }

    public function test_accepted_agreement_keeps_snapshot_when_settings_change(): void
    {
        $affiliate = $this->affiliate();
        $terms = app(AffiliateTermsService::class);
        $terms->accept($affiliate, Request::create('/terms', 'POST'));
        $acceptance = PartnerAgreementAcceptance::query()->where('partner_id', $affiliate->id)->first();
        $this->assertNotNull($acceptance);
        $this->assertNotSame('', (string) $acceptance->rendered_text);

        $acceptance->update(['rendered_text' => "SNAPSHOT-TARGET-10\n\n## Locked terms\nThis signed copy stays at 10."]);
        $fresh = $acceptance->fresh();
        $sections = $terms->documentSections($affiliate, $fresh);
        $joined = collect($sections)->pluck('body')->implode("\n");

        $this->assertStringContainsString('SNAPSHOT-TARGET-10', $joined.$fresh->rendered_text);
        $this->assertSame($fresh->rendered_text, $acceptance->fresh()->rendered_text);
    }

    public function test_share_cooldown_uses_calendar_date_copy(): void
    {
        $share = file_get_contents(resource_path('views/site/affiliate/share.blade.php'));
        $this->assertStringContainsString('code_cooldown_on', $share);
        $this->assertStringContainsString('kf-premium-panel', $share);
        $this->assertStringNotContainsString('diffInDays($nextCodeChangeAt)', $share);
    }

    public function test_payment_gate_lines_include_original_benefit_and_net(): void
    {
        $affiliate = $this->affiliate([
            'affiliate_code' => 'LINE10',
            'application_discount_percent' => 10,
        ]);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id]);
        $quote = app(PaymentGateService::class)->quote($customer, 10000, 'application_fee');
        $keys = array_column($quote['lines'] ?? [], 'key');

        $this->assertContains('base', $keys);
        $this->assertContains('affiliate', $keys);
        $this->assertSame(1000.0, (float) $quote['affiliate_discount']);
        $this->assertSame(9000.0, (float) $quote['cash_due']);
    }

    private function affiliate(array $overrides = []): Vendor
    {
        return Vendor::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'vendor'])->id,
            'vendor_number' => 'AFF-CL-'.random_int(100, 999),
            'name' => 'Closure Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '2557123'.random_int(10000, 99999),
            'affiliate_code' => 'CL'.random_int(1000, 9999),
            'affiliate_kyc_status' => 'verified',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
            'application_discount_percent' => 10,
        ], $overrides));
    }

    private function customer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'C-CL'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Closed',
            'last_name' => 'Borrower',
            'phone' => '+255700'.random_int(100000, 999999),
            'country_code' => 'TZ',
        ], $overrides));
    }

    private function product(): LoanProduct
    {
        return LoanProduct::create([
            'code' => 'CL'.random_int(100, 999),
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
    }
}
