<?php

namespace App\Services;

use App\Models\MarketplaceAsset;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketplaceAssetService
{
    public function syncDeposit(MarketplaceAsset $asset, ?Vendor $vendor = null): void
    {
        $asset->deposit_markup_percent = app(AssetLendingService::class)->defaultDepositMarkupPercent();
        $asset->customer_deposit = $asset->computeCustomerDeposit();

        if ($asset->exists) {
            $asset->save();
        }
    }

    /** @param array<string, mixed> $data */
    public function prepareForSave(array $data, ?MarketplaceAsset $existing = null): array
    {
        if (empty($data['slug']) && ! empty($data['title'])) {
            $data['slug'] = Str::slug($data['title']).'-'.Str::lower(Str::random(4));
        }

        if (! empty($data['vendor_id'])) {
            $vendor = Vendor::find($data['vendor_id']);
            if ($vendor) {
                $data['supplier_name'] = $data['supplier_name'] ?? $vendor->name;
            }
        }

        if (blank($data['supplier_name'] ?? null)) {
            $data['supplier_name'] = trim((string) ($data['title'] ?? '')) ?: 'Marketplace supplier';
        }

        if (blank($data['waiting_period_days'] ?? null)) {
            $data['waiting_period_days'] = app(AssetLendingService::class)->defaultWaitingPeriodDays();
        }

        $data['deposit_markup_percent'] = app(AssetLendingService::class)->defaultDepositMarkupPercent();

        $asset = $existing ?? new MarketplaceAsset($data);
        $asset->fill($data);
        $this->syncDeposit($asset, $asset->vendor ?? null);

        if (blank($data['weekly_installment'] ?? null) || (float) ($data['weekly_installment'] ?? 0) <= 0) {
            $asset->weekly_installment = $this->suggestWeeklyInstallment($asset);
        }

        $data['customer_deposit'] = $asset->customer_deposit;
        $data['deposit_markup_percent'] = $asset->deposit_markup_percent;
        $data['weekly_installment'] = $asset->weekly_installment;

        return $data;
    }

    public function suggestWeeklyInstallment(MarketplaceAsset $asset, ?float $monthlyRate = null): float
    {
        $assetValue = (float) ($asset->asset_value ?? 0);
        $deposit = (float) ($asset->customer_deposit ?: $asset->computeCustomerDeposit());
        $tenureMonths = max(1, (int) ($asset->max_tenure_months ?? 12));
        $loanPrincipal = max(0, $assetValue - $deposit);

        if ($loanPrincipal <= 0) {
            return 0.0;
        }

        $monthlyRate ??= app(AssetLendingService::class)->defaultMonthlyRate();
        $monthlyRate = max(0.01, min(0.35, $monthlyRate));

        $totalRepayable = $loanPrincipal * (1 + ($monthlyRate * $tenureMonths));
        $weeks = max(1, (int) round($tenureMonths * 4.33));

        return round($totalRepayable / $weeks, 2);
    }

    /** @param array<int, UploadedFile> $newFiles */
    public function syncPhotos(MarketplaceAsset $asset, array $newFiles = [], array $removePaths = []): void
    {
        $photos = collect($asset->photos ?? []);

        foreach ($removePaths as $path) {
            if ($photos->contains($path)) {
                Storage::disk('public')->delete($path);
                $photos = $photos->reject(fn ($p) => $p === $path);
            }
        }

        foreach ($newFiles as $file) {
            if ($photos->count() >= 4) {
                break;
            }
            if ($file instanceof UploadedFile) {
                $photos->push($file->store("marketplace/{$asset->id}", 'public'));
            }
        }

        $asset->update(['photos' => $photos->values()->take(4)->all()]);
    }

    /**
     * Resolve a marketplace asset from DB, or materialize a config/demo asset into the DB
     * so reservation and payment flows work for the same IDs shown on browse/detail pages.
     */
    public function resolveOrMaterialize(string $assetId): ?MarketplaceAsset
    {
        $existing = MarketplaceAsset::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('availability_status')->orWhere('availability_status', 'available'))
            ->where(function ($q) use ($assetId): void {
                $q->where('slug', $assetId);
                if (is_numeric($assetId)) {
                    $q->orWhere('id', (int) $assetId);
                }
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $config = collect(config('asset_marketplace.assets', []))->firstWhere('id', $assetId);
        if (! $config) {
            return null;
        }

        $supplierDeposit = (float) ($config['deposit'] ?? 0);
        $assetValue = (float) ($config['asset_value'] ?? ($supplierDeposit * 1.4));

        $prepared = $this->prepareForSave([
            'slug'               => $config['id'],
            'category'           => $config['category'] ?? 'other',
            'title'              => $config['title'] ?? 'Marketplace asset',
            'description'        => $config['description'] ?? null,
            'supplier_name'      => $config['vendor'] ?? ($config['supplier'] ?? 'Demo supplier'),
            'asset_value'        => $assetValue,
            'supplier_deposit'   => $supplierDeposit,
            'weekly_installment' => (float) ($config['weekly_installment'] ?? 0),
            'max_tenure_months'  => (int) ($config['max_tenure_months'] ?? 12),
            'photos'             => $config['photos'] ?? [],
            'is_active'          => true,
        ]);

        return MarketplaceAsset::updateOrCreate(
            ['slug' => $prepared['slug']],
            $prepared,
        );
    }

    /** @return array<string, mixed> */
    public function validationRules(?MarketplaceAsset $existing = null): array
    {
        return [
            'category'               => ['required', 'string', 'max:40'],
            'title'                  => ['required', 'string', 'max:150'],
            'description'            => ['nullable', 'string'],
            'serial_number'          => ['nullable', 'string', 'max:80'],
            'chassis_number'         => ['nullable', 'string', 'max:80'],
            'engine_number'          => ['nullable', 'string', 'max:80'],
            'insurance_policy_number'=> ['nullable', 'string', 'max:80'],
            'insurance_expires_at'   => ['nullable', 'date'],
            'asset_value'            => ['required', 'numeric', 'min:0'],
            'supplier_deposit'       => ['required', 'numeric', 'min:0'],
            'weekly_installment'     => ['nullable', 'numeric', 'min:0'],
            'max_tenure_months'      => ['required', 'integer', 'min:1', 'max:120'],
            'waiting_period_days'    => ['nullable', 'integer', 'min:0', 'max:90'],
            'is_active'              => ['nullable', 'boolean'],
            'photos'                 => ['nullable', 'array', 'max:4'],
            'photos.*'               => ['image', 'max:5120'],
            'remove_photos'          => ['nullable', 'array'],
            'remove_photos.*'        => ['string', 'max:255'],
        ];
    }
}
