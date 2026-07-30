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
        unset($data['insurance_available'], $data['photos'], $data['remove_photos'], $data['cover_path']);

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

    /**
     * Normalize uploaded photo payloads from a single file or photos[] list.
     *
     * @param  mixed  $files
     * @return list<UploadedFile>
     */
    public function normalizeUploadedPhotos(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter(
            $files,
            fn ($file) => $file instanceof UploadedFile && $file->isValid()
        ));
    }

    /**
     * Sync marketplace photos with collateral-style slot replace + explicit cover.
     *
     * Cover is persisted as photos[0]. New uploads may arrive as photos[0..3] slot keys.
     * cover_path may be an existing path or "__new_{slot}" for a just-uploaded slot.
     *
     * @param  array<int, UploadedFile>|UploadedFile|null  $newFiles
     * @param  array<int, string>  $removePaths
     */
    public function syncPhotos(
        MarketplaceAsset $asset,
        array|UploadedFile|null $newFiles = [],
        array $removePaths = [],
        ?string $coverPath = null,
    ): void {
        $maxPhotos = min(4, $this->maxPhotos());
        $removePaths = array_values(array_filter(array_map('strval', $removePaths)));

        $slots = array_fill(0, $maxPhotos, null);
        foreach (array_values(array_filter($asset->photos ?? [])) as $i => $path) {
            if ($i < $maxPhotos) {
                $slots[$i] = $path;
            }
        }

        foreach ($removePaths as $path) {
            foreach ($slots as $i => $existing) {
                if ($existing !== $path) {
                    continue;
                }
                if (! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
                    Storage::disk('public')->delete($path);
                }
                $slots[$i] = null;
            }
        }

        $filesBySlot = [];
        if ($newFiles instanceof UploadedFile) {
            $filesBySlot[0] = $newFiles;
        } elseif (is_array($newFiles)) {
            foreach ($newFiles as $key => $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $filesBySlot[(int) $key] = $file;
                }
            }
        }

        $newPathBySlot = [];
        foreach ($filesBySlot as $slot => $file) {
            if ($slot < 0 || $slot >= $maxPhotos) {
                continue;
            }
            $previous = $slots[$slot];
            if (is_string($previous) && $previous !== ''
                && ! str_starts_with($previous, 'http://')
                && ! str_starts_with($previous, 'https://')) {
                Storage::disk('public')->delete($previous);
            }

            $stored = $file->store("marketplace/{$asset->id}", 'public');
            if (is_string($stored) && $stored !== '') {
                $slots[$slot] = $stored;
                $newPathBySlot[$slot] = $stored;
            }
        }

        $final = array_values(array_filter($slots, fn ($path) => filled($path)));

        if (is_string($coverPath) && str_starts_with($coverPath, '__new_')) {
            $slot = (int) substr($coverPath, strlen('__new_'));
            $coverPath = $newPathBySlot[$slot] ?? null;
        }

        if (is_string($coverPath) && $coverPath !== '' && in_array($coverPath, $final, true)) {
            $final = array_values(array_merge(
                [$coverPath],
                array_values(array_filter($final, fn ($path) => $path !== $coverPath))
            ));
        }

        $asset->forceFill([
            'photos' => array_slice($final, 0, $maxPhotos),
        ])->save();
    }

    /** @param array<int, UploadedFile>|UploadedFile|null $newFiles
     *  @param array<int, string> $removePaths
     */
    public function validateMinimumPhotos(?MarketplaceAsset $existing, array|UploadedFile|null $newFiles = [], array $removePaths = []): void
    {
        $existingCount = count($existing?->photos ?? []);
        $removedCount = count(array_intersect($removePaths, $existing?->photos ?? []));
        $remaining = max(0, $existingCount - $removedCount);
        $total = $remaining + count($this->normalizeUploadedPhotos($newFiles));

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
            'photos.*'               => ['nullable', 'image', 'max:5120'],
            'remove_photos'          => ['nullable', 'array'],
            'remove_photos.*'        => ['string', 'max:2048'],
            'cover_path'             => ['nullable', 'string', 'max:2048'],
            'vendor_id'              => [$requireSupplier ? 'required' : 'nullable', 'exists:partners,id'],
        ];
    }
}
