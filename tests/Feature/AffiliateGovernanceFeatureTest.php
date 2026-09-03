<?php

namespace Tests\Feature;

use App\Models\AffiliateEvent;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PartnerApplication;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateEligibilityService;
use App\Services\AffiliateEvaluationService;
use App\Services\AffiliateLifecycleService;
use App\Services\AffiliateMembershipService;
use App\Services\AffiliateService;
use App\Services\AffiliateSettingsService;
use App\Services\AffiliateTermsService;
use App\Services\PartnerEnrollmentService;
use App\Support\AffiliatePerformanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AffiliateGovernanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function commerciallyEligibleAffiliate(array $overrides = []): Vendor
    {
        return Vendor::create(array_merge([
            'vendor_number' => 'AFF-GOV-'.random_int(100, 999),
            'name' => 'Governance Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '255712340'.random_int(100, 999),
            'affiliate_code' => 'GOV'.random_int(100, 999),
            'affiliate_kyc_status' => 'verified',
            'affiliate_lifecycle_status' => AffiliateLifecycleService::ACTIVE,
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonths(4),
            'membership_expires_at' => now()->addYear(),
        ], $overrides));
    }

    public function test_assessment_period_defaults_to_quarterly_from_settings(): void
    {
        $settings = app(AffiliateSettingsService::class);

        $this->assertSame(90, $settings->evaluationPeriodDays());
        $this->assertSame(90, $settings->volumeMinActiveDays());
        $this->assertSame(25000.0, AffiliateMembershipService::config()['fee_amount_individual']);
        $this->assertSame(50000.0, AffiliateMembershipService::config()['fee_amount_company']);
        $this->assertTrue($settings->kpiCatalog()['qualified_referrals']['enabled']);
        $this->assertFalse($settings->kpiCatalog()['applications']['enabled']);
    }

    public function test_apply_page_is_a_four_part_application_with_no_fee(): void
    {
        $this->get(route('site.affiliate.apply'))
            ->assertOk()
            ->assertSee(__('site.affiliate_apply.title'), false)
            ->assertSee(__('site.affiliate_apply.section_you'), false)
            ->assertSee(__('site.affiliate_apply.section_experience'), false)
            ->assertSee(__('site.affiliate_apply.section_market'), false)
            ->assertSee(__('site.affiliate_apply.section_declaration'), false)
            ->assertSee(__('site.affiliate_apply.subtitle'), false)
            ->assertSee(__('site.affiliate_apply.coverage_online'), false)
            ->assertDontSee('coverage_regions', false)
            ->assertDontSee('application/review fee', false)
            ->assertDontSee('Application fee', false);
    }

    public function test_approval_and_account_activation_do_not_make_promo_operational(): void
    {
        $application = PartnerApplication::create([
            'type' => 'affiliate',
            'partner_category' => 'affiliate',
            'applicant_category' => 'individual',
            'full_name' => 'Approved Unpaid',
            'email' => 'unpaid-aff@example.com',
            'phone' => '255712349900',
            'business_name' => 'Approved Unpaid',
            'region' => 'Dar es Salaam',
            'status' => 'approved',
        ]);

        $partner = app(PartnerEnrollmentService::class)->convertToPartner($application);

        $this->assertSame('inactive', $partner->status);
        $this->assertSame('nationwide', $partner->coverage_type);
        $this->assertSame([], $partner->regions ?? []);
        $this->assertNotSame('active', $partner->membership_status);
        $this->assertFalse(app(AffiliateMembershipService::class)->isActive($partner));
        $this->assertNotEmpty($partner->affiliate_code);

        $partner->update([
            'status' => 'active',
            'affiliate_kyc_status' => 'verified',
            'affiliate_lifecycle_status' => AffiliateLifecycleService::ACTIVE,
        ]);

        $this->assertNull(app(AffiliateService::class)->findByCode($partner->affiliate_code));
        $this->assertFalse(app(AffiliateEligibilityService::class)->canAttributeNewReferral($partner->fresh()));
        $this->assertContains('membership_inactive', app(AffiliateEligibilityService::class)->for($partner->fresh())['reasons']);
    }

    public function test_membership_is_required_before_referral_attribution(): void
    {
        $unpaid = $this->commerciallyEligibleAffiliate([
            'affiliate_code' => 'GOVUNP1',
            'membership_status' => null,
            'membership_started_at' => null,
            'membership_expires_at' => null,
        ]);
        $paid = $this->commerciallyEligibleAffiliate(['affiliate_code' => 'GOVPAID1']);

        $this->assertNull(app(AffiliateService::class)->findByCode('GOVUNP1'));
        $this->assertNotNull(app(AffiliateService::class)->findByCode('GOVPAID1'));

        $this->get('/aff/GOVUNP1')
            ->assertRedirect(route('site.register.borrower'))
            ->assertSessionHas('warning', __('site.affiliate_portal.link_not_verified'));

        $this->get('/aff/GOVPAID1')
            ->assertRedirect()
            ->assertSessionHas(\App\Services\AffiliateAttributionService::CLAIM_SESSION_KEY);

        $customer = Customer::create([
            'customer_number' => 'C-GOV-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Referred',
            'last_name' => 'Borrower',
            'phone' => '+255700111222',
        ]);
        app(AffiliateService::class)->attachAffiliate($customer, 'GOVUNP1');
        $this->assertNull($customer->fresh()->affiliate_vendor_id);

        app(AffiliateService::class)->attachAffiliate($customer->fresh(), 'GOVPAID1');
        $this->assertSame($paid->id, $customer->fresh()->affiliate_vendor_id);
        $this->assertDatabaseHas('affiliate_events', [
            'partner_id' => $paid->id,
            'event_type' => 'registration',
            'customer_id' => $customer->id,
        ]);
    }

    public function test_terms_use_settings_values_and_snapshots_are_immutable(): void
    {
        $affiliate = $this->commerciallyEligibleAffiliate();
        $terms = app(AffiliateTermsService::class);

        $rendered = $terms->render($affiliate, 'en');
        $this->assertStringContainsString('every 3 months', $rendered);
        $this->assertStringContainsString('10', $rendered);
        $this->assertStringContainsString('25,000', $rendered);

        $sw = $terms->render($affiliate, 'sw');
        $this->assertStringContainsString('kila miezi 3', $sw);

        $acceptance = $terms->accept($affiliate, Request::create('/affiliate-portal/terms', 'POST'));
        $original = $acceptance->rendered_text;

        $eval = app(AffiliateSettingsService::class)->evaluationSettings();
        $eval['period_days'] = 180;
        $eval['policy_version'] = 99;
        $eval['kpis']['qualified_referrals']['target'] = 15;
        $eval['monthly_registration_target'] = 15;
        Setting::set('affiliates.evaluation', $eval);

        $updated = $terms->render($affiliate->fresh(), 'en');
        $this->assertStringContainsString('180', $updated);
        $this->assertSame($original, $acceptance->fresh()->rendered_text);
        $this->assertNotSame($original, $updated);
        $this->assertSame(1, $acceptance->fresh()->policy_version);
    }

    public function test_membership_payment_uses_partner_membership_payin_path(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $affiliate = $this->commerciallyEligibleAffiliate([
            'user_id' => $user->id,
            'affiliate_code' => 'GOVPAY01',
            'membership_status' => null,
            'membership_started_at' => null,
            'membership_expires_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('site.affiliate.membership.pay'))
            ->assertRedirect(route('site.affiliate.terms'));

        app(AffiliateTermsService::class)->accept($affiliate, Request::create('/terms', 'POST'));

        $this->actingAs($user)
            ->get(route('site.affiliate.membership.pay'))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.membership_pay'), false)
            ->assertDontSee(__('site.affiliate_portal.membership_confirm_paid'), false);

        $this->assertDatabaseHas('customer_payments', [
            'partner_id' => $affiliate->id,
            'payment_type' => 'partner_membership',
            'status' => 'awaiting_payment',
        ]);
        $this->assertTrue(CustomerPayment::query()->where('partner_id', $affiliate->id)->exists());
    }

    public function test_evaluation_warns_suspends_and_recovers_with_structured_reasons(): void
    {
        Setting::set('affiliates.evaluation', [
            'auto_apply_actions' => true,
            'auto_recover' => true,
            'period_days' => 30,
            'min_events_for_scoring' => 3,
            'monthly_registration_target' => 10,
            'volume_min_active_days' => 0,
            'volume_misses_before_nudge' => 1,
            'volume_misses_before_watchlist' => 2,
            'volume_misses_before_suspend' => 1,
            'watchlist_risk_score' => 90,
            'watchlist_fraud_score' => 90,
            'suspend_risk_score' => 95,
            'suspend_fraud_score' => 95,
        ]);

        $affiliate = $this->commerciallyEligibleAffiliate([
            'affiliate_code' => 'GOVEVAL1',
            'created_at' => now()->subYear(),
            'membership_started_at' => now()->subYear(),
        ]);

        $service = app(AffiliateEvaluationService::class);
        $first = $service->evaluatePartner($affiliate->fresh(), now()->subDays(30)->startOfDay(), now()->endOfDay(), applyActions: true);

        $this->assertSame('suspend', $first->recommendation);
        $this->assertSame('suspended', $first->action_taken);
        $this->assertSame(AffiliatePerformanceStatus::SUSPENDED, $affiliate->fresh()->affiliate_performance_status);
        $this->assertSame(AffiliateLifecycleService::ACTIVE, $affiliate->fresh()->affiliate_lifecycle_status);
        $this->assertStringContainsString('Required qualified referrals: 10', (string) $affiliate->fresh()->affiliate_lifecycle_note);
        $this->assertStringContainsString('Actual: 0', (string) $affiliate->fresh()->affiliate_lifecycle_note);
        $this->assertNull(app(AffiliateService::class)->findByCode('GOVEVAL1'));

        foreach (range(1, 10) as $i) {
            AffiliateEvent::create([
                'vendor_id' => $affiliate->id,
                'event_type' => 'registration',
            ]);
        }

        $recovered = $service->evaluatePartner($affiliate->fresh(), now()->subDays(30)->startOfDay(), now()->endOfDay(), applyActions: true);

        $this->assertSame(AffiliatePerformanceStatus::GOOD_STANDING, $affiliate->fresh()->affiliate_performance_status);
        $this->assertSame('recovered', $recovered->action_taken);
        $this->assertNotNull(app(AffiliateService::class)->findByCode('GOVEVAL1'));
        $this->assertDatabaseHas('affiliate_events', [
            'partner_id' => $affiliate->id,
            'event_type' => 'registration',
        ]);
    }

    public function test_admin_affiliate_profile_shows_performance_business_membership_and_agreements(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $affiliate = $this->commerciallyEligibleAffiliate(['affiliate_code' => 'GOVPROF1']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $affiliate))
            ->assertOk()
            ->assertSee('Business', false)
            ->assertSee('Performance', false)
            ->assertSee('Membership', false)
            ->assertSee('Agreements', false)
            ->assertSee('Operational eligibility', false);
    }

    public function test_public_apply_stores_payload_without_payment(): void
    {
        Storage::fake('public');

        $this->post(route('site.affiliate.apply.post'), [
            'applicant_category' => 'individual',
            'full_name' => 'Governance Applicant',
            'email' => 'gov-apply@example.com',
            'phone' => '+255712345801',
            'region' => 'Dar es Salaam',
            'occupation' => 'Shop owner',
            'sales_experience' => 'I sell airtime and assist customers daily.',
            'languages' => ['sw', 'en'],
            'why_affiliate' => 'I already advise customers on mobile money.',
            'acquisition_methods' => ['existing_customers', 'community'],
            'monthly_reach' => '11-30',
            'first_10_customers' => 'I will start with my regular shop customers this month.',
            'declaration_accurate' => '1',
            'declaration_standards' => '1',
            'declaration_no_fees' => '1',
            'declaration_not_employment' => '1',
            'doc_national_id_front' => \Illuminate\Http\UploadedFile::fake()->image('id-front.jpg'),
            'doc_national_id_back' => \Illuminate\Http\UploadedFile::fake()->image('id-back.jpg'),
        ])->assertRedirect();

        $application = PartnerApplication::query()->where('email', 'gov-apply@example.com')->first();
        $this->assertNotNull($application);
        $this->assertSame('pending', $application->status);
        $this->assertSame('Shop owner', $application->payload['occupation'] ?? null);
        $this->assertSame([], $application->coverage_regions ?? []);
        $this->assertSame(0, CustomerPayment::query()->count());
    }

    public function test_public_apply_does_not_require_region_or_coverage(): void
    {
        Storage::fake('public');

        $this->post(route('site.affiliate.apply.post'), [
            'applicant_category' => 'individual',
            'full_name' => 'Online Only Affiliate',
            'email' => 'online-aff@example.com',
            'phone' => '+255712345802',
            'occupation' => 'Content creator',
            'sales_experience' => 'I promote products to an online audience.',
            'languages' => ['sw'],
            'why_affiliate' => 'I already advise followers about loans.',
            'acquisition_methods' => ['social_media'],
            'monthly_reach' => '100+',
            'first_10_customers' => 'I will share from my existing online audience.',
            'declaration_accurate' => '1',
            'declaration_standards' => '1',
            'declaration_no_fees' => '1',
            'declaration_not_employment' => '1',
            'doc_national_id_front' => \Illuminate\Http\UploadedFile::fake()->image('id-front.jpg'),
            'doc_national_id_back' => \Illuminate\Http\UploadedFile::fake()->image('id-back.jpg'),
        ])->assertRedirect();

        $application = PartnerApplication::query()->where('email', 'online-aff@example.com')->first();
        $this->assertNotNull($application);
        $this->assertNull($application->region);
        $this->assertSame([], $application->coverage_regions ?? []);

        $application->update(['status' => 'approved']);
        $partner = app(PartnerEnrollmentService::class)->convertToPartner($application->fresh());
        $this->assertSame('nationwide', $partner->coverage_type);
        $this->assertSame([], $partner->regions ?? []);
    }

    public function test_premium_affiliate_skips_volume_kpi_but_keeps_compliance_holds(): void
    {
        Setting::set('affiliates.evaluation', [
            'auto_apply_actions' => true,
            'auto_recover' => true,
            'period_days' => 30,
            'min_events_for_scoring' => 3,
            'monthly_registration_target' => 10,
            'volume_min_active_days' => 0,
            'volume_misses_before_nudge' => 1,
            'volume_misses_before_watchlist' => 2,
            'volume_misses_before_suspend' => 1,
            'watchlist_risk_score' => 90,
            'watchlist_fraud_score' => 90,
            'suspend_risk_score' => 95,
            'suspend_fraud_score' => 95,
        ]);

        $affiliate = $this->commerciallyEligibleAffiliate([
            'affiliate_code' => 'GOVPREM1',
            'affiliate_premium' => true,
            'created_at' => now()->subYear(),
            'membership_started_at' => now()->subYear(),
            'affiliate_performance_status' => AffiliatePerformanceStatus::SUSPENDED,
        ]);

        $service = app(AffiliateEvaluationService::class);
        $first = $service->evaluatePartner($affiliate->fresh(), now()->subDays(30)->startOfDay(), now()->endOfDay(), applyActions: true);

        $this->assertNotSame('suspended', $first->action_taken);
        $this->assertSame(AffiliatePerformanceStatus::PREMIUM, $affiliate->fresh()->affiliate_performance_status);
        $this->assertSame(AffiliateLifecycleService::ACTIVE, $affiliate->fresh()->affiliate_lifecycle_status);
        $this->assertNotNull(app(AffiliateService::class)->findByCode('GOVPREM1'));
        $this->assertFalse(AffiliatePerformanceStatus::blocksNewBusiness($affiliate->fresh()->affiliate_performance_status));

        $standing = $service->currentStanding($affiliate->fresh());
        $this->assertTrue($standing['premium']);
        $this->assertSame(AffiliatePerformanceStatus::PREMIUM, $standing['status']);
        $this->assertSame(0, $standing['needed_referrals']);
        $this->assertSame(__('site.affiliate_portal.next_action_premium'), $standing['next_action']);

        $compliance = $service->applyRecommendation($affiliate->fresh(), 'suspend', [
            'consecutive_misses' => 9,
            'onboarding' => false,
        ], fraud: 100, risk: 100);
        $this->assertSame('suspended', $compliance);
        $this->assertSame(AffiliateLifecycleService::SUSPENDED, $affiliate->fresh()->affiliate_lifecycle_status);
        $this->assertNull(app(AffiliateService::class)->findByCode('GOVPREM1'));
    }

    public function test_admin_can_mark_premium_affiliate_and_force_nationwide_coverage(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $affiliate = $this->commerciallyEligibleAffiliate([
            'affiliate_code' => 'GOVPREM2',
            'affiliate_performance_status' => AffiliatePerformanceStatus::SUSPENDED,
            'coverage_type' => 'regions',
            'regions' => ['Dar es Salaam'],
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.partners.update', $affiliate), [
                'name' => $affiliate->name,
                'category' => 'affiliate',
                'status' => 'active',
                'phone' => $affiliate->phone,
                'affiliate_code' => 'GOVPREM2',
                'affiliate_premium' => '1',
                'coverage_type' => 'regions',
                'regions' => ['Dar es Salaam'],
            ])
            ->assertRedirect(route('admin.partners.show', $affiliate));

        $fresh = $affiliate->fresh();
        $this->assertTrue($fresh->isPremiumAffiliate());
        $this->assertSame('nationwide', $fresh->coverage_type);
        $this->assertSame([], $fresh->regions ?? []);
        $this->assertSame(AffiliatePerformanceStatus::PREMIUM, $fresh->affiliate_performance_status);
        $this->assertNotNull(app(AffiliateService::class)->findByCode('GOVPREM2'));
    }
}
