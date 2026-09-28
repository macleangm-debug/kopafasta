<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateCommercialTermsService;
use App\Services\AffiliateCommissionCalculatorService;
use App\Services\AffiliateService;
use App\Services\AffiliateTermsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AffiliatePremiumCommercialPassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_hub_has_one_affiliate_rate_configuration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.affiliates'))
            ->assertOk()
            ->assertSee('Default commission (%)', false)
            ->assertSee('Standard Affiliate Agreement', false)
            ->assertSee('Premium Affiliate Agreement', false)
            ->assertDontSee('Premium Affiliate default rates', false)
            ->assertDontSee('name="premium_default_commission_percent"', false);
    }

    public function test_standard_create_cannot_store_individual_rate_overrides(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), $this->createPayload([
                'name' => 'Standard Rates Affiliate',
                'affiliate_premium' => '0',
                'commercial_rate_source' => 'negotiated',
                'affiliate_commission_percent' => '25',
                'registration_discount_percent' => '25',
                'application_discount_percent' => '25',
                'plus_discount_percent' => '25',
            ]))
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'Standard Rates Affiliate')->first();
        $this->assertNotNull($partner);
        $this->assertFalse($partner->isPremiumAffiliate());
        $this->assertNull($partner->affiliate_commission_percent);
        $this->assertSame('standard', data_get($partner->metadata, 'commercial.rate_source'));

        $rates = app(AffiliateCommercialTermsService::class)->effectiveRates($partner);
        $this->assertSame(10.0, $rates['affiliate_commission_percent']);
        $this->assertSame(10.0, app(AffiliateCommissionCalculatorService::class)->percentFor($partner));
    }

    public function test_premium_create_inherits_settings_rates_by_default(): void
    {
        Setting::set('affiliates.default_commission_percent', 12);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.create', ['category' => 'affiliate']))
            ->assertOk()
            ->assertSee('Use standard Affiliate rates', false)
            ->assertSee('Use negotiated rates', false);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), $this->createPayload([
                'name' => 'Premium Standard Rates',
                'affiliate_premium' => '1',
                'commercial_rate_source' => 'standard',
            ]))
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'Premium Standard Rates')->first();
        $this->assertTrue($partner->isPremiumAffiliate());
        $this->assertSame('standard', data_get($partner->metadata, 'commercial.rate_source'));
        $this->assertNull($partner->affiliate_commission_percent);
        $this->assertSame(12.0, app(AffiliateCommercialTermsService::class)->effectiveRates($partner)['affiliate_commission_percent']);
    }

    public function test_premium_negotiated_override_belongs_only_to_that_affiliate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), $this->createPayload([
                'name' => 'Premium A',
                'affiliate_premium' => '1',
                'commercial_rate_source' => 'standard',
                'phone' => '255710000301',
            ]))
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), $this->createPayload([
                'name' => 'Premium B',
                'affiliate_premium' => '1',
                'commercial_rate_source' => 'negotiated',
                'affiliate_commission_percent' => '18',
                'registration_discount_percent' => '15',
                'application_discount_percent' => '8',
                'plus_discount_percent' => '5',
                'commercial_note' => 'Board-approved creator deal',
                'phone' => '255710000302',
            ]))
            ->assertRedirect();

        $a = Vendor::query()->where('name', 'Premium A')->first();
        $b = Vendor::query()->where('name', 'Premium B')->first();
        $this->assertSame(10.0, app(AffiliateService::class)->commissionPercent($a));
        $this->assertSame(18.0, app(AffiliateCommissionCalculatorService::class)->percentFor($b));
        $this->assertSame(15.0, app(AffiliateService::class)->registrationDiscountPercent($b));
        $this->assertSame('Board-approved creator deal', data_get($b->metadata, 'commercial.note'));
        $this->assertNull(data_get($a->metadata, 'commercial.note'));
        $this->assertSame(10.0, (float) Setting::get('affiliates.default_commission_percent', 10));
    }

    public function test_affiliate_360_shows_rate_source_and_contract_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = $this->premiumAffiliate([
            'name' => '360 Premium',
            'affiliate_commission_percent' => 22,
            'metadata' => [
                'commercial' => [
                    'rate_source' => 'negotiated',
                    'effective_from' => '2026-09-01',
                    'note' => 'Internal negotiation file NF-22',
                    'rates' => ['affiliate_commission_percent' => 22],
                ],
            ],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $partner))
            ->assertOk()
            ->assertSee('Commercial terms', false)
            ->assertSee('Premium Affiliate', false)
            ->assertSee('Negotiated rates', false)
            ->assertSee('22%', false)
            ->assertSee('Internal negotiation file NF-22', false)
            ->assertSee('View agreement', false)
            ->assertDontSee('0/target', false);
    }

    public function test_premium_contract_snapshots_effective_rates_and_survives_settings_change(): void
    {
        $affiliate = $this->premiumAffiliate([
            'affiliate_commission_percent' => 17,
            'registration_discount_percent' => 14,
            'application_discount_percent' => 9,
            'metadata' => [
                'commercial' => [
                    'rate_source' => 'negotiated',
                    'effective_from' => now()->toDateString(),
                    'rates' => [
                        'registration_discount_percent' => 14,
                        'application_discount_percent' => 9,
                        'affiliate_commission_percent' => 17,
                        'plus_discount_percent' => 6,
                    ],
                ],
                'plus_discount_percent' => 6,
            ],
        ]);

        $terms = app(AffiliateTermsService::class);
        $rendered = $terms->render($affiliate, 'en');
        $this->assertStringContainsString('17%', $rendered);
        $this->assertStringContainsString('Negotiated rates', $rendered);
        $this->assertStringContainsString('Premium Affiliate', $rendered);

        $acceptance = $terms->accept($affiliate, Request::create('/affiliate/terms', 'POST'), 'en');
        $this->assertSame('17%', data_get($acceptance->settings_snapshot, 'commission_percent'));
        $this->assertSame('negotiated', data_get($acceptance->settings_snapshot, 'rate_source'));
        $this->assertStringContainsString('17%', (string) $acceptance->rendered_text);

        Setting::set('affiliates.default_commission_percent', 3);
        Setting::set('affiliates.terms.premium.body_en', 'CHANGED PREMIUM TEMPLATE {{commission_percent}}');

        $this->assertSame('17%', data_get($acceptance->fresh()->settings_snapshot, 'commission_percent'));
        $this->assertStringContainsString('17%', (string) $acceptance->fresh()->rendered_text);
        $this->assertStringNotContainsString('CHANGED PREMIUM TEMPLATE', (string) $acceptance->fresh()->rendered_text);
        $this->assertSame(17.0, app(AffiliateCommissionCalculatorService::class)->percentFor($affiliate->fresh()));
    }

    public function test_commercial_term_and_classification_changes_are_consequential_and_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = $this->premiumAffiliate(['name' => 'Change Terms Affiliate']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.affiliate-commercial-terms', $partner), [
                'rate_source' => 'negotiated',
                'affiliate_commission_percent' => '19',
                'registration_discount_percent' => '11',
                'application_discount_percent' => '11',
                'plus_discount_percent' => '11',
                'effective_from' => now()->toDateString(),
                'reason' => 'Creator-network launch terms.',
                'confirmed' => '1',
            ])
            ->assertRedirect(route('admin.partners.show', $partner));

        $fresh = $partner->fresh();
        $this->assertSame(19.0, (float) $fresh->affiliate_commission_percent);
        $this->assertSame('negotiated', data_get($fresh->metadata, 'commercial.rate_source'));
        $this->assertTrue(AuditLog::query()->where('event', 'affiliate.commercial_terms.changed')->where('auditable_id', $fresh->id)->exists());

        $this->actingAs($admin, 'admin')
            ->put(route('admin.partners.update', $fresh), [
                'name' => $fresh->name,
                'category' => 'affiliate',
                'status' => 'active',
                'phone' => $fresh->phone,
                'affiliate_premium' => '0',
                'affiliate_commission_percent' => '4',
            ])
            ->assertRedirect();

        $this->assertTrue($fresh->fresh()->isPremiumAffiliate());
        $this->assertSame(19.0, (float) $fresh->fresh()->affiliate_commission_percent);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.affiliate-classification', $fresh), [
                'affiliate_premium' => '0',
                'reason' => 'Moving back to performance-governed Standard.',
                'confirmed' => '1',
            ])
            ->assertRedirect();

        $standard = $fresh->fresh();
        $this->assertFalse($standard->isPremiumAffiliate());
        $this->assertNull($standard->affiliate_commission_percent);
        $this->assertTrue(AuditLog::query()->where('event', 'affiliate.classification.changed')->where('auditable_id', $standard->id)->exists());
        $this->assertSame(10.0, app(AffiliateCommissionCalculatorService::class)->percentFor($standard));
    }

    public function test_premium_portal_keeps_analytics_and_hides_target_progress(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $affiliate = $this->premiumAffiliate([
            'user_id' => $user->id,
            'name' => 'Portal Premium',
        ]);

        $html = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.affiliate.dashboard'))
            ->assertOk()
            ->assertSee('PREMIUM', false)
            ->getContent();

        $this->assertStringNotContainsString('0/target', $html);
        $this->assertStringNotContainsString('below target', $html);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.affiliate.performance'))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.premium_badge', [], 'en'), false)
            ->assertDontSee(__('site.affiliate_portal.more_needed', ['count' => 10], 'en'), false);
    }

    /** @param  array<string, mixed>  $overrides */
    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Affiliate Partner',
            'category' => 'affiliate',
            'status' => 'inactive',
            'phone' => '255710000'.random_int(100, 999),
            'coverage_type' => 'nationwide',
            'activation_mode' => 'draft',
        ], $overrides);
    }

    /** @param  array<string, mixed>  $overrides */
    private function premiumAffiliate(array $overrides = []): Vendor
    {
        return Vendor::create(array_merge([
            'vendor_number' => 'AFF-PREM-'.random_int(100, 999),
            'name' => 'Premium Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'activated_at' => now()->subDay(),
            'phone' => '255712340'.random_int(100, 999),
            'affiliate_code' => 'PREM'.random_int(100, 999),
            'affiliate_premium' => true,
            'affiliate_kyc_status' => 'verified',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ], $overrides));
    }
}
