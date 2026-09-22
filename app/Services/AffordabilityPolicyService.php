<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Canonical affordability repayment-to-income policy.
 * Settings Hub is the source of truth; consumers must not hardcode ratios.
 */
class AffordabilityPolicyService
{
    public const DEFAULT_RATIO = 0.3333;

    public const DEFAULT_RATIO_PCT = 33.33;

    public function __construct(
        private readonly CountrySettingsService $countries,
    ) {}

    /** Fraction 0–1 used in capacity arithmetic. */
    public function repaymentRatio(?string $countryCode = null): float
    {
        $underwriting = Setting::get('underwriting.repayment_ratio');
        if ($underwriting !== null && $underwriting !== '') {
            return max(0.01, min(1.0, round((float) $underwriting, 4)));
        }

        $credit = Setting::get('credit.repayment_ratio');
        if ($credit !== null && $credit !== '') {
            return max(0.01, min(1.0, round((float) $credit, 4)));
        }

        return max(0.01, min(1.0, round((float) $this->countries->repaymentRatio($countryCode), 4)));
    }

    public function repaymentRatioPct(?string $countryCode = null): float
    {
        return round($this->repaymentRatio($countryCode) * 100, 2);
    }

    public function hardGateEnabled(): bool
    {
        $raw = Setting::get('underwriting.hard_affordability_gate');

        return $raw === null ? true : (bool) $raw;
    }

    public function policyVersion(): int
    {
        return max(1, (int) Setting::get('underwriting.affordability_policy_version', 1));
    }

    public function effectiveFrom(): ?string
    {
        $value = Setting::get('underwriting.affordability_effective_from');

        return filled($value) ? (string) $value : null;
    }

    /**
     * Persist ratio from Settings UI. Bumps version when the ratio changes.
     * Keeps country/credit keys in sync so legacy readers stay consistent.
     */
    public function persistRatioPct(float $pct, ?string $countryCode = null): void
    {
        $pct = max(1.0, min(100.0, round($pct, 2)));
        $ratio = round($pct / 100, 4);
        $previous = Setting::get('underwriting.repayment_ratio');
        $version = max(1, (int) Setting::get('underwriting.affordability_policy_version', 1));
        $changed = $previous !== null && abs((float) $previous - $ratio) > 0.00005;
        if ($previous === null) {
            $version = max(1, $version);
        } elseif ($changed) {
            $version++;
        }

        $code = strtolower($countryCode ?: $this->countries->defaultCountryCode());

        Setting::setMany([
            'underwriting.repayment_ratio' => $ratio,
            'underwriting.repayment_ratio_pct' => $pct,
            'underwriting.affordability_policy_version' => $version,
            'underwriting.affordability_effective_from' => Setting::get('underwriting.affordability_effective_from') && ! $changed && $previous !== null
                ? Setting::get('underwriting.affordability_effective_from')
                : now()->toDateString(),
            'underwriting.hard_affordability_gate' => Setting::get('underwriting.hard_affordability_gate') === null
                ? true
                : (bool) Setting::get('underwriting.hard_affordability_gate'),
            'credit.repayment_ratio' => $ratio,
            "country.{$code}.repayment_ratio" => $ratio,
        ]);
    }

    /** Ensure defaults exist without changing an already-configured ratio. */
    public function ensureDefaultsSeeded(): void
    {
        if (Setting::get('underwriting.repayment_ratio') === null
            && Setting::get('credit.repayment_ratio') === null) {
            $this->persistRatioPct(self::DEFAULT_RATIO_PCT);
            Setting::set('underwriting.affordability_policy_version', 1);
            Setting::set('underwriting.affordability_effective_from', now()->toDateString());
            Setting::set('underwriting.hard_affordability_gate', true);

            return;
        }

        if (Setting::get('underwriting.repayment_ratio') === null) {
            $ratio = $this->repaymentRatio();
            Setting::set('underwriting.repayment_ratio', $ratio);
            Setting::set('underwriting.repayment_ratio_pct', round($ratio * 100, 2));
        }
        if (! Setting::get('underwriting.affordability_policy_version')) {
            Setting::set('underwriting.affordability_policy_version', 1);
        }
        if (! Setting::get('underwriting.affordability_effective_from')) {
            Setting::set('underwriting.affordability_effective_from', now()->toDateString());
        }
        if (Setting::get('underwriting.hard_affordability_gate') === null) {
            Setting::set('underwriting.hard_affordability_gate', true);
        }
    }

    /**
     * Compact snapshot for underwriting decisions and decision letters.
     *
     * @return array{
     *   policy_version: int,
     *   repayment_ratio: float,
     *   repayment_ratio_pct: float,
     *   affordability_ratio_label: string,
     *   hard_gate: bool,
     *   effective_from: ?string,
     *   snapshotted_at: string
     * }
     */
    public function snapshot(?string $countryCode = null): array
    {
        $this->ensureDefaultsSeeded();
        $pct = $this->repaymentRatioPct($countryCode);
        $ratio = $this->repaymentRatio($countryCode);

        return [
            'policy_version' => $this->policyVersion(),
            'repayment_ratio' => $ratio,
            'repayment_ratio_pct' => $pct,
            'affordability_ratio_label' => $this->formatRatioLabel($pct),
            'hard_gate' => $this->hardGateEnabled(),
            'effective_from' => $this->effectiveFrom(),
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }

    public function formatRatioLabel(float $pct): string
    {
        $trimmed = rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.');

        return $trimmed.'%';
    }

    /**
     * Prefer a decision-time snapshot over live Settings.
     *
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public function resolveFromStored(?array $stored): array
    {
        if (is_array($stored) && isset($stored['repayment_ratio_pct'])) {
            $pct = (float) $stored['repayment_ratio_pct'];

            return [
                'policy_version' => (int) ($stored['policy_version'] ?? 0),
                'repayment_ratio' => (float) ($stored['repayment_ratio'] ?? round($pct / 100, 4)),
                'repayment_ratio_pct' => $pct,
                'affordability_ratio_label' => (string) ($stored['affordability_ratio_label'] ?? $this->formatRatioLabel($pct)),
                'hard_gate' => (bool) ($stored['hard_gate'] ?? true),
                'effective_from' => $stored['effective_from'] ?? null,
                'snapshotted_at' => $stored['snapshotted_at'] ?? null,
                'income_basis' => $stored['income_basis'] ?? null,
            ];
        }

        return $this->snapshot();
    }
}
