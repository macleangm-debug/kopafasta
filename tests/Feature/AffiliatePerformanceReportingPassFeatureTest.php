<?php

namespace Tests\Feature;

use App\Models\AffiliateEvent;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateAttributionService;
use App\Services\AffiliatePortalPresenter;
use App\Services\AffiliateService;
use App\Services\AffiliateTermsService;
use App\Services\CustomerPaymentService;
use App\Services\Messaging\TransactionalMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AffiliatePerformanceReportingPassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_labels_are_performance_and_utendaji(): void
    {
        $en = include lang_path('en/site.php');
        $sw = include lang_path('sw/site.php');

        $this->assertSame('Performance', data_get($en, 'affiliate_portal.nav_performance'));
        $this->assertSame('Reports', data_get($en, 'affiliate_portal.nav_reports'));
        $this->assertSame('Utendaji', data_get($sw, 'affiliate_portal.nav_performance'));
        $this->assertSame('Ripoti', data_get($sw, 'affiliate_portal.nav_reports'));
        $this->assertSame('Shiriki & Pata', data_get($sw, 'affiliate_portal.nav_share'));
        $this->assertSame('Commission earned', data_get($en, 'affiliate_portal.funnel_earned'));
        $this->assertSame('Registered members', data_get($en, 'affiliate_portal.funnel_registered'));
        $this->assertSame('Wanachama waliosajiliwa', data_get($sw, 'affiliate_portal.funnel_registered'));
        $this->assertSame('Available to withdraw', data_get($en, 'affiliate_portal.figure_available'));
        $this->assertSame('Inayoweza kutolewa', data_get($sw, 'affiliate_portal.figure_available'));
        $this->assertSame(':achieved of :target registered members', data_get($en, 'affiliate_portal.kpi_of'));
        $this->assertSame(':achieved kati ya wanachama :target waliosajiliwa', data_get($sw, 'affiliate_portal.kpi_of'));
        $this->assertStringNotContainsString('qualifying members', strtolower((string) data_get($en, 'affiliate_portal.kpi_of')));
        $this->assertSame('Member', data_get($en, 'affiliate_portal.col_member'));
        $this->assertSame('Mwanachama', data_get($sw, 'affiliate_portal.col_member'));
        $this->assertStringContainsString('Vigezo na Masharti vinatumika.', (string) data_get($sw, 'affiliate_portal.share_invite_with_benefit'));
        $this->assertStringNotContainsString('Customer', (string) data_get($en, 'affiliate_portal.funnel_registered'));
        $this->assertStringNotContainsString('Successful', (string) data_get($en, 'affiliate_portal.funnel_registered'));
        $this->assertStringNotContainsString('Qualifying members', (string) data_get($en, 'affiliate_portal.funnel_registered'));
    }

    public function test_promo_only_commission_does_not_inflate_referral_member_counts(): void
    {
        $affiliate = $this->affiliate();
        $member = $this->customer(['member_no' => 'KPF-TZ-PRO1']);
        AffiliateEvent::create([
            'vendor_id' => $affiliate->id,
            'event_type' => 'commission_kopafasta_plus',
            'customer_id' => $member->id,
            'commission_amount' => 90,
            'landing_page' => 'payment:148',
        ]);

        $funnel = app(AffiliatePortalPresenter::class)->performance($affiliate)['funnel'];
        $this->assertSame(0, $funnel['registered']);
        $this->assertSame(0, $funnel['applied']);
        $this->assertSame(0, $funnel['qualifying']);
        $this->assertSame(90.0, $funnel['earned']);
        $this->assertSame(1, $funnel['commission_transactions']);
        $this->assertSame(['visited', 'registered', 'applied'], app(AffiliatePortalPresenter::class)->visibleFunnelKeys());
        $this->assertSame(['visited', 'applied'], app(AffiliatePortalPresenter::class)->overviewFunnelKeys());
        $this->assertNotContains('qualifying', app(AffiliatePortalPresenter::class)->visibleFunnelKeys());
        $this->assertNotContains('successful', app(AffiliatePortalPresenter::class)->visibleFunnelKeys());

        AffiliateEvent::create([
            'vendor_id' => $affiliate->id,
            'event_type' => 'registration',
            'customer_id' => $member->id,
        ]);
        $after = app(AffiliatePortalPresenter::class)->performance($affiliate)['funnel'];
        $this->assertSame(1, $after['registered']);
        $this->assertSame(1, $after['qualifying']);
        $this->assertSame(90.0, $after['earned']);
    }

    public function test_referral_pipeline_uses_membership_number_not_borrower_name(): void
    {
        $affiliate = $this->affiliate();
        $customer = $this->customer([
            'first_name' => 'Halima',
            'last_name' => 'Khamis',
            'member_no' => 'KPF-TZ-WLN5',
        ]);

        AffiliateEvent::create([
            'vendor_id' => $affiliate->id,
            'event_type' => 'registration',
            'customer_id' => $customer->id,
            'landing_page' => 'https://staging.kopafasta.com/register/borrower?aff=UATSTD01',
        ]);
        AffiliateEvent::create([
            'vendor_id' => $affiliate->id,
            'event_type' => 'commission_application_fee',
            'customer_id' => $customer->id,
            'commission_amount' => 90,
            'landing_page' => 'payment:1',
        ]);

        $row = app(AffiliatePortalPresenter::class)->performance($affiliate)['pipeline']->first();
        $this->assertSame('KPF-TZ-WLN5', $row['member_no']);
        $this->assertSame(90.0, $row['commission_amount']);
        $this->assertNotEmpty($row['source']);
        $this->assertNotEmpty($row['stage']);

        $html = $this->actingAs($affiliate->user)
            ->get(route('site.affiliate.performance', ['tab' => 'overview']))
            ->assertOk()
            ->assertSee('KPF-TZ-WLN5', false)
            ->assertSee(__('site.affiliate_portal.funnel_earned'), false)
            ->assertSee(__('site.affiliate_portal.figure_available'), false)
            ->assertSee(__('site.affiliate_portal.funnel_applied'), false)
            ->assertSee('lg:text-right', false)
            ->assertDontSee('Qualifying members', false)
            ->assertDontSee('Successful customers', false)
            ->assertDontSee('Successful members', false)
            ->getContent();

        $this->assertStringNotContainsString('Halima', $html);
        $this->assertStringNotContainsString('Khamis', $html);
        $this->assertStringNotContainsString('H. Khamis', $html);
    }

    public function test_monthly_report_has_month_selector_and_uses_existing_totals(): void
    {
        $affiliate = $this->affiliate(['name' => 'UAT Standard Affiliate', 'vendor_number' => 'PT-AF-TZ-WON4']);
        AffiliateEvent::create([
            'vendor_id' => $affiliate->id,
            'event_type' => 'click',
        ]);
        AffiliateEvent::create([
            'vendor_id' => $affiliate->id,
            'event_type' => 'commission_application_fee',
            'commission_amount' => 90,
            'customer_id' => $this->customer(['member_no' => 'KPF-TZ-REP1'])->id,
        ]);

        $this->actingAs($affiliate->user)
            ->get(route('site.affiliate.reports', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('UAT Standard Affiliate', false)
            ->assertSee('PT-AF-TZ-WON4', false)
            ->assertSee(__('site.affiliate_portal.report_activity'), false)
            ->assertSee(__('site.affiliate_portal.funnel_earned'), false)
            ->assertSee(__('site.affiliate_portal.funnel_registered'), false)
            ->assertSee(__('site.affiliate_portal.report_available'), false)
            ->assertSee(__('site.affiliate_portal.report_pending'), false)
            ->assertSee('name="month"', false)
            ->assertSee('monthSheet', false)
            ->assertDontSee(__('site.affiliate_portal.report_conversion'), false)
            ->assertDontSee('Qualifying members', false)
            ->assertDontSee(__('site.affiliate_portal.view_monthly_report'), false);
    }

    public function test_share_message_is_compact_and_settings_gated(): void
    {
        $affiliate = $this->affiliate(['affiliate_code' => 'UATSTD01', 'name' => 'UAT Standard Affiliate']);
        $en = app(AffiliateService::class)->shareInvitation($affiliate, 'en');
        $sw = app(AffiliateService::class)->shareInvitation($affiliate, 'sw');

        $this->assertStringContainsString('1. ', $en);
        $this->assertStringContainsString('Use promo: UATSTD01 or the referral link:', $en);
        $this->assertStringContainsString('Terms and Conditions apply.', $en);
        $this->assertStringNotContainsString('registration fee', strtolower($en));
        $this->assertStringContainsString('1. Punguzo', $sw);
        $this->assertStringContainsString('Tumia promo: UATSTD01 au kiungo cha rufaa:', $sw);
        $this->assertStringContainsString('Vigezo na Masharti vinatumika.', $sw);
        $this->assertStringNotContainsString('ada ya usajili', $sw);

        Setting::set('affiliates.applies_to', array_merge(
            config('affiliates.applies_to'),
            ['registration_fee' => true]
        ));
        $withRegistration = app(AffiliateService::class)->shareInvitation($affiliate->fresh(), 'en');
        $this->assertStringContainsString('registration fee', strtolower($withRegistration));
    }

    public function test_checkout_benefits_cannot_recreate_a_member_relationship_from_a_leftover_claim(): void
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
            'reference' => 'PAY-LEFTOVER',
            'provider_meta' => ['pricing' => ['gross' => 1000]],
        ]);

        $request = Request::create('/borrower/payments/'.$payment->id, 'GET');
        app(AffiliateAttributionService::class)->establishClaim($request, $affiliate, 'link', 'UATSTD01');

        app(CustomerPaymentService::class)->applyCheckoutBenefits($payment, false, null);

        $this->assertNull($customer->fresh()->affiliate_vendor_id);
        $this->assertNull(app(AffiliateAttributionService::class)->customerClaim($customer->fresh()));
        $this->assertStringNotContainsString(
            'connectFromPendingClaim',
            file_get_contents(app_path('Services/CustomerPaymentService.php'))
        );
    }

    public function test_daily_commission_summary_command_sends_one_notice(): void
    {
        Setting::set(TransactionalMessagingService::SETTING_ENABLED, true);
        Setting::set(TransactionalMessagingService::SETTING_CHANNELS, [
            'sms' => false,
            'email' => false,
            'in_app' => true,
            'whatsapp' => false,
            'push' => false,
        ]);

        $affiliate = $this->affiliate();
        AffiliateEvent::create([
            'vendor_id' => $affiliate->id,
            'event_type' => 'commission_application_fee',
            'commission_amount' => 90,
        ]);
        AffiliateEvent::create([
            'vendor_id' => $affiliate->id,
            'event_type' => 'commission_kopafasta_plus',
            'commission_amount' => 100,
        ]);

        $this->artisan('affiliate:send-daily-commission-summaries', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame(1, NotificationLog::query()->where('template', 'affiliate_commission_daily_summary')->count());
        $this->assertStringNotContainsString(
            'affiliate_commission_earned',
            (string) strstr(file_get_contents(app_path('Services/AffiliateService.php')), 'function accrueCommission')
        );
    }

    private function affiliate(array $overrides = []): Vendor
    {
        $affiliate = Vendor::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'vendor'])->id,
            'vendor_number' => 'AFF-PR-'.random_int(100, 999),
            'name' => 'Performance Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '2557123'.random_int(10000, 99999),
            'affiliate_code' => 'PR'.random_int(1000, 9999),
            'affiliate_kyc_status' => 'verified',
            'affiliate_lifecycle_status' => 'active',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ], $overrides));
        app(AffiliateTermsService::class)->accept($affiliate, Request::create('/terms', 'POST'));

        return $affiliate->fresh();
    }

    private function customer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'C-PR'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Mushi',
            'phone' => '+255600'.random_int(100000, 999999),
            'country_code' => 'TZ',
        ], $overrides));
    }
}
