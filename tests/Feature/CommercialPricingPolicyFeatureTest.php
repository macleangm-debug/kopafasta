<?php

namespace Tests\Feature;

use App\Models\PartnerApplication;
use App\Models\Setting;
use App\Services\AffiliateApplicationFeePaymentService;
use App\Services\CommercialPricingProfileService;
use App\Services\CountrySettingsService;
use App\Services\GpsPricingService;
use App\Services\MembershipService;
use App\Services\RecoveryPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialPricingPolicyFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_profile_sets_owner_test_amounts(): void
    {
        $this->seed(\Database\Seeders\ChargesFeeSeeder::class);
        $this->seed(\Database\Seeders\PublicLoanProductsSeeder::class);

        $result = app(CommercialPricingProfileService::class)->apply('staging');

        $this->assertSame('staging', $result['environment']);
        $this->assertSame(1000.0, (float) \App\Models\LoanProduct::query()->where('code', 'IL')->value('application_fee_amount'));
        $this->assertSame(1000.0, (float) \App\Models\LoanProduct::query()->where('code', 'AB')->value('application_fee_amount'));
        $this->assertSame(1000.0, (float) \App\Models\LoanProduct::query()->where('code', 'AL')->value('application_fee_amount'));
        $this->assertSame(0.0, (float) \App\Models\ChargesFee::query()->where('code', 'EARLY_FEE')->value('amount'));
        $this->assertSame(1000.0, (float) Setting::get('affiliates.application_fee_amount'));
        $this->assertFalse((bool) Setting::get('country.tz.borrower_membership_allowed'));
    }

    public function test_production_profile_refuses_without_confirm(): void
    {
        $this->expectException(\RuntimeException::class);
        app(CommercialPricingProfileService::class)->apply('production');
    }

    public function test_tanzania_borrower_membership_gate_defaults_off(): void
    {
        $tz = app(CountrySettingsService::class)->forCode('TZ');
        $this->assertFalse($tz['borrower_membership_allowed']);
        $this->assertFalse(MembershipService::isRequiredForCountry('TZ'));
    }

    public function test_gps_estimate_applies_markup_to_install_and_monitoring_separately(): void
    {
        Setting::setMany([
            'partner_defaults.gps_installer.base_cost' => 50_000,
            'partner_defaults.gps_installer.monitoring_monthly' => 20_000,
            'partner_defaults.gps_installer.has_markup' => true,
            'partner_defaults.gps_installer.markup_percent' => 10,
        ]);

        $estimate = app(GpsPricingService::class)->estimate(12);

        $this->assertSame(55000.0, $estimate['device_borrower']);
        $this->assertSame(22000.0, $estimate['monthly_monitoring_borrower']);
        $this->assertSame(55000.0 + (22000.0 * 12), $estimate['total']);
    }

    public function test_recovery_percentage_markup_is_of_base_not_partner_fee(): void
    {
        $charge = app(RecoveryPolicyService::class)->calculateRecoveryCharge(
            100_000,
            'call_center',
            10.0,
            3.0,
        );

        $this->assertSame(10_000.0, $charge['partner_amount']);
        $this->assertSame(3_000.0, $charge['company_amount']);
        $this->assertSame(13_000.0, $charge['total_charge']);
    }

    public function test_affiliate_application_fee_opens_payment_and_awaits_fee_status(): void
    {
        Setting::set('affiliates.application_fee_amount', 1000);

        $application = PartnerApplication::query()->create([
            'type' => 'affiliate',
            'partner_category' => 'affiliate',
            'applicant_category' => 'individual',
            'full_name' => 'Test Affiliate',
            'email' => 'affiliate@example.com',
            'phone' => '255712345678',
            'business_name' => 'Test Affiliate',
            'status' => 'pending',
            'payload' => [],
        ]);

        $payment = app(AffiliateApplicationFeePaymentService::class)->open($application->fresh());

        $this->assertSame('affiliate_application_fee', $payment->payment_type);
        $this->assertSame(1000.0, (float) $payment->amount);
        $this->assertSame('awaiting_fee', $application->fresh()->status);
        $this->assertNotEmpty(data_get($application->fresh()->payload, 'application_fee.pay_token'));
    }
}
