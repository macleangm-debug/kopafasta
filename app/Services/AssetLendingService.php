<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\Setting;
use App\Models\Vendor;

class AssetLendingService
{
    public function settings(): array
    {
        return array_merge(
            [
                'markup_base'                    => config('asset_lending.markup_base', 'deposit'),
                'default_deposit_markup_percent' => 10,
                'default_waiting_period_days'    => 7,
                'insurance_expiry_warning_days'  => 30,
                'default_monthly_rate_percent'   => 12,
            ],
            Setting::group('asset_lending'),
        );
    }

    public function defaultDepositMarkupPercent(): float
    {
        return (float) ($this->settings()['default_deposit_markup_percent'] ?? 10);
    }

    public function defaultWaitingPeriodDays(): int
    {
        return max(0, (int) ($this->settings()['default_waiting_period_days'] ?? 7));
    }

    public function insuranceExpiryWarningDays(): int
    {
        return max(1, (int) ($this->settings()['insurance_expiry_warning_days'] ?? 30));
    }

    public function defaultMonthlyRate(): float
    {
        $percent = (float) ($this->settings()['default_monthly_rate_percent'] ?? config('asset_lending.default_monthly_rate', 0.12) * 100);

        return max(0, min(1, $percent / 100));
    }

    /** @return array{status: string, label: string, tone: string, detail: string|null} */
    public function insuranceStatus(?\DateTimeInterface $expiresAt): array
    {
        if (! $expiresAt) {
            return [
                'status' => 'missing',
                'label'  => 'Insurance expiry not recorded',
                'tone'   => 'amber',
                'detail' => 'Add policy expiry on the marketplace asset before approval.',
            ];
        }

        $expiry = \Illuminate\Support\Carbon::parse($expiresAt)->startOfDay();
        $today = now()->startOfDay();
        $warningDays = $this->insuranceExpiryWarningDays();

        if ($expiry->lt($today)) {
            return [
                'status' => 'expired',
                'label'  => 'Insurance expired',
                'tone'   => 'red',
                'detail' => 'Expired '.$expiry->format('d M Y').'. Request updated certificate from borrower.',
            ];
        }

        if ($expiry->lte($today->copy()->addDays($warningDays))) {
            return [
                'status' => 'expiring',
                'label'  => 'Insurance expiring soon',
                'tone'   => 'amber',
                'detail' => 'Expires '.$expiry->format('d M Y').' ('.$today->diffInDays($expiry).' days).',
            ];
        }

        return [
            'status' => 'valid',
            'label'  => 'Insurance valid',
            'tone'   => 'emerald',
            'detail' => 'Expires '.$expiry->format('d M Y').'.',
        ];
    }

    public function markupBase(): string
    {
        $base = (string) ($this->settings()['markup_base'] ?? 'deposit');

        return in_array($base, ['deposit', 'asset_price'], true) ? $base : 'deposit';
    }

    public function isAssetLendingProduct(?LoanProduct $product): bool
    {
        return $product && is_marketplace_loan_product($product->code);
    }

    public function isAssetLendingApplication(LoanApplication $application): bool
    {
        $application->loadMissing('product');

        return $this->isAssetLendingProduct($application->product);
    }

    /** @return array<string, string> */
    public function categoryOptions(): array
    {
        return collect(config('asset_lending.categories', []))
            ->mapWithKeys(fn (array $row, string $key) => [$key => $row['label'] ?? $key])
            ->all();
    }

    public function normalizeCategory(?string $category): string
    {
        $category = (string) $category;

        if (array_key_exists($category, config('asset_lending.categories', []))) {
            return $category;
        }

        return config('asset_lending.legacy_category_map.'.$category, $category ?: 'other');
    }

    /** @return array<string, mixed> */
    public function categoryRequirements(?string $category): array
    {
        $key = $this->normalizeCategory($category);

        return config('asset_lending.categories.'.$key, config('asset_lending.categories.other', []));
    }

    public function requiresGps(?string $category): bool
    {
        return (bool) ($this->categoryRequirements($category)['gps_required'] ?? false);
    }

    public function computeCustomerDeposit(MarketplaceAsset $asset): float
    {
        $markupPercent = (float) ($asset->deposit_markup_percent ?? 0);
        $base = $this->markupBase();

        if ($base === 'asset_price') {
            $principal = (float) ($asset->asset_value ?? 0);
        } else {
            $principal = (float) ($asset->supplier_deposit ?? 0);
        }

        if ($principal <= 0) {
            return 0.0;
        }

        $markup = round($principal * ($markupPercent / 100), 2);

        if ($base === 'asset_price') {
            return round($principal + $markup, 2);
        }

        return round($principal + $markup, 2);
    }

    public function depositMarkupAmount(MarketplaceAsset $asset): float
    {
        $supplierDeposit = (float) ($asset->supplier_deposit ?? 0);
        $customerDeposit = (float) ($asset->customer_deposit ?: $this->computeCustomerDeposit($asset));

        return max(0, round($customerDeposit - $supplierDeposit, 2));
    }

    public function supplierType(Vendor $vendor): string
    {
        $type = (string) ($vendor->supplier_type ?? config('asset_lending.default_supplier_type', 'managed_loan'));

        return array_key_exists($type, config('asset_lending.supplier_types', []))
            ? $type
            : 'managed_loan';
    }

    public function isManagedLoanSupplier(Vendor $vendor): bool
    {
        return $this->supplierType($vendor) === 'managed_loan';
    }
}
