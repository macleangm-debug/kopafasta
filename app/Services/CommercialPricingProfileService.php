<?php

namespace App\Services;

use App\Models\ChargesFee;
use App\Models\LoanProduct;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Owner-approved commercial tariffs (2026-09-05).
 * Settings Hub + loan_products remain runtime truth after apply.
 * Never rewrites historical CustomerPayment / LoanFee / application fee snapshots.
 */
class CommercialPricingProfileService
{
    /** @return array<string, mixed> */
    public function production(): array
    {
        return [
            'products' => [
                'IL' => 10_000,
                'GL' => 10_000,
                'AB' => 25_000,
                'AL' => 50_000,
                'SL' => 10_000,
                'FC' => 10_000,
                'KB' => 10_000,
                'BP' => 10_000,
                'EL' => 10_000,
                'EM' => 10_000,
                'WL' => 10_000,
                'SAL-12' => 10_000,
            ],
            'catalog' => [
                'APP_FEE' => 10_000,
                'ORIG_FEE' => 2.0,
                'DISB_FEE' => 10_000,
                'REG_POST_FEE' => 40_000,
                'INS_FEE' => 1.0,
                'EARLY_FEE' => 0.0,
                'RESTR_FEE' => 10_000,
                'VAL_FEE' => 50_000,
                'VAL_POST_FEE' => 50_000,
            ],
            'valuer' => ['base_cost' => 45_454.55, 'markup_percent' => 10.0, 'has_markup' => true], // ~50k borrower; prefer exact below
            'valuation_borrower' => 50_000,
            'gps' => [
                'base_cost' => 50_000,
                'monitoring_monthly' => 20_000,
                'has_markup' => true,
                'markup_percent' => 10.0,
            ],
            'plus_tz' => 36_000,
            'affiliates_membership' => [
                'fee_amount_individual' => 30_000,
                'fee_amount_company' => 50_000,
                'fee_amount' => 50_000,
            ],
            'affiliate_application_fee' => 10_000,
            'origination_percent' => 2.0,
            'loan_insurance_percent' => 1.0,
            'collateral_insurance_percent' => 3.5,
        ];
    }

    /** Staging / UAT reduced test amounts — same engines, different Settings. */
    public function staging(): array
    {
        return [
            'products' => [
                'IL' => 1_000,
                'GL' => 1_000,
                'AB' => 1_000,
                'AL' => 1_000,
                'SL' => 1_000,
                'FC' => 1_000,
                'KB' => 1_000,
                'BP' => 1_000,
                'EL' => 1_000,
                'EM' => 1_000,
                'WL' => 1_000,
                'SAL-12' => 1_000,
            ],
            'catalog' => [
                'APP_FEE' => 1_000,
                'ORIG_FEE' => 1.0,
                'DISB_FEE' => 1_000,
                'REG_POST_FEE' => 1_000,
                'INS_FEE' => 1.0,
                'EARLY_FEE' => 0.0,
                'RESTR_FEE' => 10_000,
                'VAL_FEE' => 10_000,
                'VAL_POST_FEE' => 10_000,
            ],
            'valuation_borrower' => 10_000,
            'gps' => [
                'base_cost' => 5_000,
                'monitoring_monthly' => 2_000,
                'has_markup' => true,
                'markup_percent' => 10.0,
            ],
            'plus_tz' => 1_000,
            'affiliates_membership' => [
                'fee_amount_individual' => 1_000,
                'fee_amount_company' => 1_000,
                'fee_amount' => 1_000,
            ],
            'affiliate_application_fee' => 1_000,
            'origination_percent' => 1.0,
            'loan_insurance_percent' => 1.0,
            'collateral_insurance_percent' => 3.5,
        ];
    }

    public function profileForEnvironment(?string $env = null): array
    {
        $env ??= app()->environment();

        return $env === 'staging' || $env === 'local' ? $this->staging() : $this->production();
    }

    /**
     * Apply prospective Settings + product fees for the current environment.
     * Refuses production unless CONFIRM_PRODUCTION_PRICING=1.
     */
    public function apply(?string $env = null): array
    {
        $env ??= app()->environment();
        if ($env === 'production' && env('CONFIRM_PRODUCTION_PRICING') !== '1') {
            throw new \RuntimeException('Refusing to apply production commercial pricing without CONFIRM_PRODUCTION_PRICING=1.');
        }

        $profile = $this->profileForEnvironment($env);
        $changed = [];

        foreach ($profile['products'] as $code => $amount) {
            $product = LoanProduct::query()->where('code', $code)->first();
            if (! $product) {
                continue;
            }
            if ((float) $product->application_fee_amount !== (float) $amount) {
                $product->update(['application_fee_amount' => $amount]);
                $changed[] = "loan_products.{$code}={$amount}";
            }
        }

        foreach ($profile['catalog'] as $code => $amount) {
            $fee = ChargesFee::query()->where('code', $code)->first();
            if (! $fee) {
                continue;
            }
            $fee->update([
                'amount' => $amount,
                'is_active' => $code === 'EARLY_FEE' ? true : $fee->is_active,
            ]);
            $changed[] = "charges_fees.{$code}={$amount}";
        }

        $gps = $profile['gps'];
        Setting::setMany([
            'partner_defaults.gps_installer.base_cost' => $gps['base_cost'],
            'partner_defaults.gps_installer.monitoring_monthly' => $gps['monitoring_monthly'],
            'partner_defaults.gps_installer.has_markup' => $gps['has_markup'],
            'partner_defaults.gps_installer.markup_percent' => $gps['markup_percent'],
            'partner_defaults.valuer.has_markup' => true,
            'partner_defaults.valuer.markup_percent' => 10,
            'affiliates.application_fee_amount' => $profile['affiliate_application_fee'],
            'underwriting.collateral_insurance_rate_percent' => $profile['collateral_insurance_percent'],
            'country.tz.borrower_membership_allowed' => false,
            'loan.allow_restructure' => false,
            'staging_payments.use_price_overrides' => false,
        ]);

        // Valuation: exact whole-TZS borrower target + partner base; markup is residual.
        $borrowerVal = (int) $profile['valuation_borrower'];
        $markupPct = 10.0;
        $platformShare = (int) round($borrowerVal * $markupPct / (100 + $markupPct));
        $valuerBase = $borrowerVal - $platformShare;
        Setting::setMany([
            'partner_defaults.valuer.base_cost' => $valuerBase,
            'partner_defaults.valuer.has_markup' => true,
            'partner_defaults.valuer.markup_percent' => $markupPct,
            'partner_defaults.valuer.borrower_amount' => $borrowerVal,
        ]);
        app(ValuationPricingService::class)->syncChargesFees();
        $quoted = (int) app(ValuationPricingService::class)->quote()['borrower_amount'];
        if ($quoted !== $borrowerVal) {
            throw new \RuntimeException("Valuation quote {$quoted} does not match commercial target {$borrowerVal}.");
        }

        $plusConfig = Setting::get('kopafasta_plus.config');
        $plusConfig = is_array($plusConfig) ? $plusConfig : [];
        data_set($plusConfig, 'plans.monthly.prices.TZ.amount', $profile['plus_tz']);
        Setting::set('kopafasta_plus.config', $plusConfig);

        $affMem = Setting::get('affiliates.membership');
        $affMem = is_array($affMem) ? $affMem : [];
        $affMem = array_merge($affMem, $profile['affiliates_membership'], ['enabled' => true]);
        Setting::set('affiliates.membership', $affMem);

        // Keep GPS_FEE catalog aligned to install+markup only (device), not full tenure bundle
        $gpsInstallBorrower = (int) round($gps['base_cost'] * (1 + $gps['markup_percent'] / 100));
        ChargesFee::query()->where('code', 'GPS_FEE')->update(['amount' => $gpsInstallBorrower]);

        Log::info('commercial_pricing.applied', ['env' => $env, 'changed' => $changed]);

        return ['environment' => $env, 'changed' => $changed, 'profile' => $profile];
    }
}
