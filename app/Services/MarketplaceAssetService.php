<?php

namespace App\Services;

use App\Models\MarketplaceAsset;
use App\Models\Setting;
use App\Models\Vendor;
use App\Support\MoneyFormat;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketplaceAssetService
{
    public function maxPhotos(): int
    {
        return max(1, (int) Setting::get('asset_lending.max_asset_photos', 4));
    }

    /** Normalize formatted money strings and insurance toggle before validation. */
    public function normalizeRequest(Request $request): void
    {
        $request->merge($this->normalizeInput($request->all()));
    }

    public function computeCustomerDepositPreview(float $assetValue, float $depositPercent): float
    {
        $supplierDeposit = round($assetValue * ($depositPercent / 100), 2);
        $markupPercent = app(AssetLendingService::class)->defaultDepositMarkupPercent();

        return round($supplierDeposit + ($supplierDeposit * ($markupPercent / 100)), 2);
    }

    /** @param array<string, mixed> $input */
    public function normalizeInput(array $input): array
    {
        foreach (['asset_value'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
                $input[$key] = MoneyFormat::toNumber($input[$key]);
            }
        }

        if (array_key_exists('deposit_percent', $input) && $input['deposit_percent'] !== null && $input['deposit_percent'] !== '') {
            $input['deposit_percent'] = MoneyFormat::toNumber($input['deposit_percent']);
        }

        $insuranceAvailable = ($input['insurance_available'] ?? '1') === '1'
            || ($input['insurance_available'] ?? true) === true
            || ($input['insurance_available'] ?? '1') === 1;

        if (! $insuranceAvailable) {
            $input['insurance_policy_number'] = null;
            $input['insurance_expires_at'] = null;
        }

        unset($input['insurance_available']);

        return $input;
    }

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
        unset($data['insurance_available'], $data['photos'], $data['remove_photos']);

        if (empty($data['slug']) && ! empty($data['title'])) {
            $data['slug'] = Str::slug($data['title']).'-'.Str::lower(Str::random(4));
        }

        if (isset($data['deposit_percent'])) {
            $assetValue = (float) ($data['asset_value'] ?? 0);
            $data['supplier_deposit'] = round($assetValue * ((float) $data['deposit_percent'] / 100), 2);
            unset($data['deposit_percent']);
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

        $data['waiting_period_days'] = app(AssetLendingService::class)->defaultWaitingPeriodDays();

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
                if (! str_starts_with((string) $path, 'http://') && ! str_starts_with((string) $path, 'https://')) {
                    Storage::disk('public')->delete($path);
                }
                $photos = $photos->reject(fn ($p) => $p === $path);
            }
        }

        $maxPhotos = $this->maxPhotos();

        foreach ($newFiles as $file) {
            if ($photos->count() >= $maxPhotos) {
                break;
            }
            if ($file instanceof UploadedFile) {
                $photos->push($file->store("marketplace/{$asset->id}", 'public'));
            }
        }

        $asset->update(['photos' => $photos->values()->take($maxPhotos)->all()]);
    }

    /** @param array<int, string> $removePaths */
    public function validateMinimumPhotos(?MarketplaceAsset $existing, array $newFiles, array $removePaths = []): void
    {
        $existingCount = count($existing?->photos ?? []);
        $removedCount = count(array_intersect($removePaths, $existing?->photos ?? []));
        $remaining = max(0, $existingCount - $removedCount);
        $total = $remaining + count(array_filter($newFiles));

        if ($total < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'photos' => 'At least one image is required.',
            ]);
        }
    }

    /**
     * Resolve a marketplace asset from DB, or materialize a config/demo asset into the DB
     * so reservation and payment flows work for the same IDs shown on browse/detail pages.
     */
    public function resolveOrMaterialize(string $assetId): ?MarketplaceAsset
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('marketplace_assets')) {
            return null;
        }

        $existing = MarketplaceAsset::query()
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
        $depositPercent = $assetValue > 0 ? round(($supplierDeposit / $assetValue) * 100, 2) : 0;

        $prepared = $this->prepareForSave([
            'slug'               => $config['id'],
            'category'           => $config['category'] ?? 'other',
            'title'              => $config['title'] ?? 'Marketplace asset',
            'description'        => $config['description'] ?? null,
            'supplier_name'      => $config['vendor'] ?? ($config['supplier'] ?? 'Demo supplier'),
            'asset_value'        => $assetValue,
            'deposit_percent'    => $depositPercent,
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
    public function validationRules(?MarketplaceAsset $existing = null, bool $requireSupplier = false): array
    {
        $maxPhotos = $this->maxPhotos();

        return [
            'insurance_available'    => ['nullable', 'in:0,1'],
            'category'               => ['required', 'string', 'max:40'],
            'title'                  => ['required', 'string', 'max:150'],
            'description'            => ['nullable', 'string'],
            'serial_number'          => ['nullable', 'string', 'max:80'],
            'chassis_number'         => ['nullable', 'string', 'max:80'],
            'engine_number'          => ['nullable', 'string', 'max:80'],
            'insurance_policy_number'=> ['nullable', 'string', 'max:80'],
            'insurance_expires_at'   => ['nullable', 'date'],
            'asset_value'            => ['required', 'numeric', 'min:0'],
            'deposit_percent'        => ['required', 'numeric', 'min:0.01', 'max:100'],
            'max_tenure_months'      => ['required', 'integer', 'min:1', 'max:120'],
            'is_active'              => ['nullable', 'boolean'],
            'photos'                 => [$existing ? 'nullable' : 'required', 'array', 'min:'.($existing ? 0 : 1), 'max:'.$maxPhotos],
            'photos.*'               => ['image', 'max:5120'],
            'remove_photos'          => ['nullable', 'array'],
            'remove_photos.*'        => ['string', 'max:255'],
            'vendor_id'              => [$requireSupplier ? 'required' : 'nullable', 'exists:partners,id'],
        ];
    }
}
