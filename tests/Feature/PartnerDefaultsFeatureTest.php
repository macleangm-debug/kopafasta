<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Setting;
use App\Models\User;
use App\Services\CollateralInsurancePartnerService;
use App\Services\GpsPricingService;
use App\Services\PartnerDefaultsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerDefaultsFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'pin_hash' => bcrypt('1234'),
        ]);
    }

    public function test_recovery_settings_page_shows_service_partner_defaults(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.settings.recovery'))
            ->assertOk()
            ->assertSee('Service rates', false)
            ->assertSee('Recovery partners', false)
            ->assertSee('Insurance partner', false)
            ->assertSee('GPS partner', false)
            ->assertSee('Valuation partner', false)
            ->assertSee('Call Center Partner', false)
            ->assertSee('Debt Collection Partner', false)
            ->assertSee(route('admin.partners.create', ['category' => 'insurance']), false);
    }

    public function test_saving_recovery_policy_persists_partner_defaults(): void
    {
        $admin = $this->admin();
        $policy = app(\App\Services\RecoveryPolicyService::class);
        $payload = [
            'grace_period_days' => 2,
            'fee_base' => 'principal',
            'auto_escalate' => 1,
            'auto_assign_call_center' => 1,
            'call_center_lead_days' => 0,
            'insurance_rate_percent' => 4.5,
            'insurance_has_markup' => 1,
            'insurance_markup_percent' => 5,
            'gps_installer_base_cost' => 120000,
            'gps_installer_monitoring_monthly' => 8000,
            'gps_installer_has_markup' => 1,
            'gps_installer_markup_percent' => 12,
            'valuer_base_cost' => 75000,
            'valuer_has_markup' => 0,
            'valuer_markup_percent' => 0,
        ];

        foreach ($policy->partnerTypes() as $type => $meta) {
            $payload["sla_days_{$type}"] = $meta['default_sla_days'];
            $payload["commission_percent_{$type}"] = $meta['default_commission_percent'];
            $payload["markup_percent_{$type}"] = $meta['default_markup_percent'];
            $payload["fee_type_{$type}"] = $meta['default_fee_type'] ?? 'percentage';
            $payload["fixed_amount_{$type}"] = $meta['default_fixed_amount'];
            $payload["priority_{$type}"] = $meta['default_priority'] ?? 99;
            $payload["loan_types_{$type}"] = $meta['default_loan_types'] ?? 'all';
            $payload["collateral_scope_{$type}"] = $meta['default_collateral_scope'] ?? 'all';
            $payload["auto_escalate_type_{$type}"] = 1;
        }

        foreach (array_keys(config('repossession_charges.asset_types', [])) as $assetType) {
            $payload["repossession_partner_cost_{$assetType}"] = 0;
            $payload["repossession_markup_{$assetType}"] = 10;
            $payload["repossession_manual_{$assetType}"] = 0;
        }

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.recovery.save'), $payload)
            ->assertRedirect();

        $defaults = app(PartnerDefaultsService::class);
        $this->assertSame(4.5, $defaults->insuranceRatePercent());
        $this->assertSame(5.0, $defaults->insuranceMarkupPercent());
        $this->assertSame(120000.0, $defaults->gpsBaseCost());
        $this->assertSame(8000.0, $defaults->gpsMonitoringMonthly());
        $this->assertSame(12.0, $defaults->gpsMarkupPercent());
        $this->assertSame(75000.0, $defaults->valuerBaseCost());
        $this->assertSame(0.0, $defaults->valuerMarkupPercent());

        $quote = app(CollateralInsurancePartnerService::class)->quote(10_000);
        $this->assertSame(450, $quote['base_premium']);
        $this->assertSame(23, $quote['markup_amount']); // 5% of 450
        $this->assertSame(473, $quote['premium']);
    }

    public function test_partner_rate_override_is_used_for_insurance_quote(): void
    {
        Setting::setMany([
            'partner_defaults.insurance.rate_percent' => 3.5,
            'partner_defaults.insurance.has_markup' => false,
            'partner_defaults.insurance.markup_percent' => 0,
        ]);

        $partner = Partner::create([
            'partner_number' => 'INS-TEST-1',
            'name' => 'Override Insurer',
            'category' => 'insurance',
            'status' => 'active',
            'markup_percent' => 10,
            'metadata' => ['service_rate_percent' => 2],
        ]);

        $quote = app(CollateralInsurancePartnerService::class)->quote(100_000, $partner);
        $this->assertSame(2.0, $quote['rate_percent']);
        $this->assertSame(10.0, $quote['markup_percent']);
        $this->assertSame(2000, $quote['base_premium']);
        $this->assertSame(200, $quote['markup_amount']);
        $this->assertSame(2200, $quote['premium']);
    }

    public function test_gps_pricing_uses_partner_defaults(): void
    {
        Setting::setMany([
            'partner_defaults.gps_installer.base_cost' => 50_000,
            'partner_defaults.gps_installer.monitoring_monthly' => 5_000,
            'partner_defaults.gps_installer.has_markup' => true,
            'partner_defaults.gps_installer.markup_percent' => 10,
        ]);

        $estimate = app(GpsPricingService::class)->estimate(12);
        $this->assertSame(50_000.0, $estimate['device_cost']);
        $this->assertSame(60_000.0, $estimate['monitoring_total']);
        $this->assertSame(11_000.0, $estimate['markup']);
        $this->assertSame(121_000.0, $estimate['total']);
    }

    public function test_add_partner_form_shows_insurance_defaults(): void
    {
        Setting::setMany([
            'partner_defaults.insurance.rate_percent' => 3.5,
            'partner_defaults.insurance.has_markup' => false,
            'partner_defaults.insurance.markup_percent' => 0,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.partners.create', ['category' => 'insurance']))
            ->assertOk()
            ->assertSee('Service partner default rates', false)
            ->assertSee('Default cover rate', false)
            ->assertSee('Override cover rate', false);
    }
}
