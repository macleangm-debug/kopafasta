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
                'deposit_deadline_working_days'  => 2,
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

    /** Working days after approval for the borrower to pay the asset deposit. */
    public function depositDeadlineWorkingDays(): int
    {
        return max(1, (int) ($this->settings()['deposit_deadline_working_days'] ?? 2));
    }

    /**
     * AL comprehensive insurance basis = approved marketplace asset price.
     * Do not invent a separate valuation or accept borrower-edited insured value for the basis.
     */
    public function insuredValueForMarketplaceAsset(MarketplaceAsset $asset): int
    {
        $value = (float) ($asset->asset_value ?? 0);
        if ($value <= 0) {
            $deposit = (float) ($asset->customer_deposit ?: $asset->computeCustomerDeposit());
            $value = max($deposit, 0);
        }

        return (int) max(0, round($value));
    }

    /**
     * AL comprehensive insurance quote from marketplace price (Settings rate).
     * Snapshots value/rate so later Settings or marketplace edits do not alter an open obligation.
     *
     * @return array{
     *   insured_value: int,
     *   rate_percent: float,
     *   markup_percent: float,
     *   effective_rate_percent: float,
     *   base_premium: int,
     *   markup_amount: int,
     *   premium: int,
     *   basis: string,
     *   marketplace_asset_id: int,
     *   snapshotted_at: string
     * }
     */
    public function comprehensiveInsuranceQuote(MarketplaceAsset $asset, ?\App\Models\Partner $partner = null): array
    {
        $insured = $this->insuredValueForMarketplaceAsset($asset);
        $quote = app(CollateralInsurancePartnerService::class)->quote($insured, $partner);

        return array_merge($quote, [
            'basis' => 'marketplace_asset_value',
            'marketplace_asset_id' => (int) $asset->id,
            'snapshotted_at' => now()->toIso8601String(),
        ]);
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
                'detail' => 'Arrange and verify comprehensive cover after approval, before asset handover.',
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

    public function arrangementLabel(string $type): string
    {
        return (string) (config('asset_lending.supplier_types.'.$type) ?? $type);
    }

    public function requiresMarketplaceValuation(?string $category): bool
    {
        return (bool) ($this->categoryRequirements($category)['valuation_required'] ?? false);
    }

    /**
     * Deposit tiers keyed on asset purchase price (Product Configuration editor; Settings storage).
     *
     * @return list<array{from: float, to: ?float, percent: float, active: bool}>
     */
    public function depositTiers(): array
    {
        $raw = $this->settings()['deposit_tiers'] ?? config('asset_lending.deposit_tiers', []);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        return $this->normalizeTierRows(is_array($raw) ? $raw : [], 'deposit');
    }

    /**
     * Financing tiers keyed on financed balance after deposit.
     *
     * @return list<array{from: float, to: ?float, monthly_rate_percent: float, method: string, max_tenure_months: int, active: bool}>
     */
    public function financingTiers(): array
    {
        $raw = $this->settings()['financing_tiers'] ?? config('asset_lending.financing_tiers', []);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        return $this->normalizeTierRows(is_array($raw) ? $raw : [], 'financing');
    }

    /**
     * Canonical Asset Lending quote: price → deposit → financed → financing → tenure → installment.
     *
     * @return array<string, mixed>
     */
    public function pricingQuoteFromAssetPrice(float $assetPrice, ?int $tenureMonths = null): array
    {
        $assetPrice = round(max(0, $assetPrice), 2);
        $depositTier = $this->matchTier($this->depositTiers(), $assetPrice);
        $depositPercent = (float) ($depositTier['percent'] ?? 0);
        $depositAmount = round($assetPrice * ($depositPercent / 100), 2);
        $financed = round(max(0, $assetPrice - $depositAmount), 2);
        $financingTier = $this->matchTier($this->financingTiers(), $financed);
        $monthlyRatePercent = (float) ($financingTier['monthly_rate_percent'] ?? 0);
        $method = (string) ($financingTier['method'] ?? 'reducing_balance');
        $maxTenure = (int) ($financingTier['max_tenure_months'] ?? 6);
        $tenure = $tenureMonths !== null ? max(1, min($maxTenure, $tenureMonths)) : $maxTenure;
        $monthlyRate = max(0, $monthlyRatePercent / 100);
        $schedule = $this->reducingBalanceSchedule($financed, $monthlyRate, $tenure);
        $installment = (float) ($schedule[0]['total_due'] ?? 0);
        $totalPayable = round($depositAmount + array_sum(array_column($schedule, 'total_due')), 2);

        return [
            'asset_price' => $assetPrice,
            'deposit_percent' => $depositPercent,
            'deposit_amount' => $depositAmount,
            'financed_amount' => $financed,
            'monthly_rate_percent' => $monthlyRatePercent,
            'rate_method' => $method,
            'tenure_months' => $tenure,
            'max_tenure_months' => $maxTenure,
            'repayment_frequency' => 'monthly',
            'installment' => round($installment, 2),
            'total_interest' => round(array_sum(array_column($schedule, 'interest')), 2),
            'total_payable' => $totalPayable,
            'application_fee' => null,
            'valuation_applicable' => false,
            'schedule' => $schedule,
            'deposit_tier_snapshot' => $depositTier,
            'financing_tier_snapshot' => $financingTier,
            'pricing_policy' => 'product_configuration_tiers',
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Tenure-keyed quotes from the same Product Configuration calculator.
     *
     * @return array<int, array<string, mixed>>
     */
    public function quotesByTenure(float $assetPrice, int $maxTenure): array
    {
        $quotes = [];
        $maxTenure = max(1, min(24, $maxTenure));
        for ($months = 1; $months <= $maxTenure; $months++) {
            $quote = $this->pricingQuoteFromAssetPrice($assetPrice, $months);
            $quotes[$months] = [
                'installment' => $quote['installment'],
                'total_payable' => $quote['total_payable'],
                'total_interest' => $quote['total_interest'],
                'deposit_amount' => $quote['deposit_amount'],
                'financed_amount' => $quote['financed_amount'],
                'repayment_frequency' => $quote['repayment_frequency'],
                'tenure_months' => $quote['tenure_months'],
            ];
        }

        return $quotes;
    }

    /**
     * Persist deposit / financing tiers. Product Configuration is the editor;
     * Settings storage remains the shared read source for quotes.
     *
     * @param  list<array<string, mixed>>  $deposit
     * @param  list<array<string, mixed>>  $financing
     */
    public function persistPricingTiers(array $deposit, array $financing): void
    {
        Setting::set('asset_lending.deposit_tiers', $this->normalizeTierRows($deposit, 'deposit'));
        Setting::set('asset_lending.financing_tiers', $this->normalizeTierRows($financing, 'financing'));
    }

    /**
     * @param  list<array<string, mixed>>  $tiers
     * @return array<string, mixed>|null
     */
    public function matchTier(array $tiers, float $amount): ?array
    {
        foreach ($tiers as $tier) {
            if (! ($tier['active'] ?? true)) {
                continue;
            }
            $from = (float) ($tier['from'] ?? 0);
            $to = $tier['to'];
            if ($amount + 0.00001 < $from) {
                continue;
            }
            if ($to === null || $to === '' || $amount <= (float) $to + 0.00001) {
                return $tier;
            }
        }

        return $tiers[array_key_last($tiers)] ?? null;
    }

    /**
     * @return list<array{installment_no: int, principal: float, interest: float, total_due: float, balance: float}>
     */
    public function reducingBalanceSchedule(float $principal, float $monthlyRate, int $tenureMonths): array
    {
        $principal = round(max(0, $principal), 2);
        $tenureMonths = max(1, $tenureMonths);
        if ($principal <= 0) {
            return [];
        }

        $principalPart = round($principal / $tenureMonths, 2);
        $balance = $principal;
        $rows = [];
        for ($i = 1; $i <= $tenureMonths; $i++) {
            $interest = round($balance * $monthlyRate, 2);
            $prin = ($i === $tenureMonths) ? round($balance, 2) : $principalPart;
            $total = round($prin + $interest, 2);
            $balance = round(max(0, $balance - $prin), 2);
            $rows[] = [
                'installment_no' => $i,
                'principal' => $prin,
                'interest' => $interest,
                'total_due' => $total,
                'balance' => $balance,
            ];
        }

        return $rows;
    }

    /**
     * Snapshot commercial terms once. Later Product/Supplier edits must not rewrite history.
     *
     * @return array<string, mixed>
     */
    public function snapshotCommercialTerms(LoanApplication $application): array
    {
        $existing = $this->commercialSnapshot($application);
        if ($existing !== []) {
            return $existing;
        }

        $application->loadMissing(['product', 'assetReservation.asset.vendor']);
        $asset = $application->assetReservation?->asset;
        if (! $this->isAssetLendingApplication($application) || ! $asset) {
            return [];
        }

        $vendor = $asset->vendor;
        $arrangement = $vendor ? $this->supplierType($vendor) : (string) config('asset_lending.default_supplier_type', 'managed_loan');
        $tenure = (int) ($application->requested_tenure_months ?? $asset->max_tenure_months ?? 6);
        $quote = $this->pricingQuoteFromAssetPrice((float) ($asset->asset_value ?? 0), $tenure);
        $quote['application_fee'] = $application->application_fee_amount;

        $snapshot = [
            'supplier_id' => $vendor?->id,
            'supplier_arrangement' => $arrangement,
            'supplier_arrangement_label' => $this->arrangementLabel($arrangement),
            'asset_id' => $asset->id,
            'asset_price' => $quote['asset_price'],
            'deposit_tier' => $quote['deposit_tier_snapshot'],
            'deposit_amount' => $quote['deposit_amount'],
            'financed_amount' => $quote['financed_amount'],
            'financing_tier' => $quote['financing_tier_snapshot'],
            'tenure_months' => $quote['tenure_months'],
            'installment' => $quote['installment'],
            'total_payable' => $quote['total_payable'],
            'application_fee' => $quote['application_fee'],
            'supplier_settlement_method' => $arrangement,
            'funding_source' => $arrangement === 'upfront_settlement' ? 'capital_partner' : null,
            'valuation_applicable' => $this->requiresMarketplaceValuation($asset->category),
            'product_settings_version' => [
                'deposit_tiers' => $this->depositTiers(),
                'financing_tiers' => $this->financingTiers(),
                'snapshotted_at' => $quote['snapshotted_at'],
            ],
            'quote' => $quote,
        ];

        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $payload['asset_lending_commercial'] = $snapshot;
        $application->update(['screening_payload' => $payload]);

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    public function commercialSnapshot(LoanApplication $application): array
    {
        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $row = $payload['asset_lending_commercial'] ?? null;

        return is_array($row) ? $row : [];
    }

    public function resolvedArrangement(LoanApplication $application): string
    {
        $snapshot = $this->commercialSnapshot($application);
        $fromSnapshot = (string) ($snapshot['supplier_arrangement'] ?? '');
        if (array_key_exists($fromSnapshot, config('asset_lending.supplier_types', []))) {
            return $fromSnapshot;
        }

        $application->loadMissing('assetReservation.asset.vendor');
        $vendor = $application->assetReservation?->asset?->vendor;

        return $vendor ? $this->supplierType($vendor) : (string) config('asset_lending.default_supplier_type', 'managed_loan');
    }

    public function isServiceCollectionApplication(LoanApplication $application): bool
    {
        return $this->resolvedArrangement($application) === 'managed_loan';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeTierRows(array $rows, string $kind): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $from = (float) ($row['from'] ?? 0);
            $toRaw = $row['to'] ?? null;
            $to = ($toRaw === null || $toRaw === '' || $toRaw === 'null') ? null : (float) $toRaw;
            $base = [
                'from' => $from,
                'to' => $to,
                'active' => filter_var($row['active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
            if ($kind === 'deposit') {
                $base['percent'] = (float) ($row['percent'] ?? 0);
            } else {
                $base['monthly_rate_percent'] = (float) ($row['monthly_rate_percent'] ?? $row['percent'] ?? 0);
                $base['method'] = (string) ($row['method'] ?? 'reducing_balance');
                $base['max_tenure_months'] = (int) ($row['max_tenure_months'] ?? 6);
            }
            $out[] = $base;
        }

        usort($out, fn ($a, $b) => $a['from'] <=> $b['from']);

        return $out;
    }
}
