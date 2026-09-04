<?php

namespace App\Services\Staging;

use App\Models\LoanProduct;
use App\Models\Setting;

/**
 * Staging payment lab: simulator / PayIn sandbox and test-price overlays.
 * Production ignores every setting in this service.
 */
class StagingPaymentsService
{
    public const PROVIDER = 'staging_simulator';

    public function isEnabled(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        if (app()->environment('staging')) {
            return true;
        }

        return app()->environment('testing') && (bool) config('staging_payments.testing_enabled', false);
    }

    public function mode(): string
    {
        if (! $this->isEnabled()) {
            return 'off';
        }

        $mode = (string) ($this->settings()['mode'] ?? config('staging_payments.mode', 'simulator'));

        return $mode === 'psp_sandbox' ? 'psp_sandbox' : 'simulator';
    }

    public function isSimulator(): bool
    {
        return $this->isEnabled() && $this->mode() === 'simulator';
    }

    public function isPspSandbox(): bool
    {
        return $this->isEnabled() && $this->mode() === 'psp_sandbox';
    }

    public function shouldAwaitProvider(): bool
    {
        return $this->isSimulator() || $this->isPspSandbox();
    }

    public function allows(string $outcome): bool
    {
        if (! $this->isSimulator()) {
            return false;
        }

        $key = match ($outcome) {
            'success' => 'allow_success',
            'pending' => 'allow_pending',
            'failed', 'cancelled', 'canceled' => 'allow_failure',
            'reversed' => 'allow_reversal',
            default => null,
        };

        if ($key === null) {
            return false;
        }

        $stored = $this->settings()[$key] ?? config('staging_payments.'.$key, true);

        return $stored === true || $stored === 1 || $stored === '1' || $stored === 'true';
    }

    public function defaultTestFee(): int
    {
        $stored = $this->settings()['default_test_fee'] ?? config('staging_payments.default_test_fee', 500);

        return max(0, (int) $stored);
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        return array_merge(config('staging_payments', []), Setting::group('staging_payments'));
    }

    /**
     * Replace the commercial amount with the staging test amount. No-op in production.
     */
    public function effective(string $kind, float $canonical, ?LoanProduct $product = null): float
    {
        if (! $this->isEnabled()) {
            return round($canonical, 2);
        }

        $key = $this->overrideKey($kind, $product);
        $overrides = $this->settings()['overrides'] ?? config('staging_payments.overrides', []);
        if (! is_array($overrides)) {
            $overrides = config('staging_payments.overrides', []);
        }

        if (array_key_exists($key, $overrides) && $overrides[$key] !== null && $overrides[$key] !== '') {
            return round((float) $overrides[$key], 2);
        }

        return round((float) $this->defaultTestFee(), 2);
    }

    public function overrideKey(string $kind, ?LoanProduct $product = null): string
    {
        $kind = strtolower(trim($kind));

        if (in_array($kind, ['application_fee', 'origination_fee'], true) && $product) {
            if (strtoupper((string) $product->code) === 'GL' || ($product->category ?? '') === 'group') {
                return 'group_application_fee';
            }
            if (strtoupper((string) $product->code) === 'AB') {
                return 'asset_backed_application_fee';
            }

            return 'application_fee';
        }

        return match ($kind) {
            'group_application_fee' => 'group_application_fee',
            'asset_backed_application_fee' => 'asset_backed_application_fee',
            'valuation_fee' => 'valuation_fee',
            'kopafasta_plus', 'plus' => 'plus',
            'registration_fee', 'membership', 'renewal_fee' => 'membership',
            'partner_membership', 'affiliate_membership' => 'partner_membership',
            default => in_array($kind, ['application_fee', 'origination_fee'], true) ? 'application_fee' : 'other',
        };
    }

    /**
     * Admin snapshot: canonical vs staging effective. Never mutates commercial settings.
     *
     * @return list<array{key: string, label: string, canonical: float, staging: float, source: string}>
     */
    public function auditRows(array $canonicals): array
    {
        $rows = [];
        foreach ($canonicals as $row) {
            $canonical = (float) ($row['canonical'] ?? 0);
            $key = (string) ($row['key'] ?? 'other');
            $rows[] = [
                'key' => $key,
                'label' => (string) ($row['label'] ?? $key),
                'canonical' => $canonical,
                'staging' => $this->isEnabled()
                    ? $this->effective($key, $canonical, $row['product'] ?? null)
                    : $canonical,
                'source' => (string) ($row['source'] ?? 'settings'),
                'changed' => $this->isEnabled() && abs($canonical - $this->effective($key, $canonical, $row['product'] ?? null)) > 0.009,
            ];
        }

        return $rows;
    }

    public function assertStaging(): void
    {
        if ($this->isEnabled()) {
            return;
        }

        abort(404);
    }
}
