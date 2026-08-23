<?php

namespace App\Services;

use App\Models\CustomerAsset;
use App\Models\PartnerDocument;
use App\Models\PartnerTask;
use App\Models\ValuationAssignment;
use App\Support\MoneyFormat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ValuationInspectionService
{
    public const DOC_PREFIX = 'valuer_photo_';

    /**
     * Pledged profile assets on this valuation job.
     *
     * @return \Illuminate\Support\Collection<int, CustomerAsset>
     */
    public function assetsForTask(PartnerTask $task)
    {
        $meta = [];
        if (is_string($task->notes)) {
            $decoded = json_decode($task->notes, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        $ids = [];
        if (! empty($meta['customer_asset_ids']) && is_array($meta['customer_asset_ids'])) {
            $ids = array_map('intval', $meta['customer_asset_ids']);
        } elseif (! empty($meta['customer_asset_id'])) {
            $ids = [(int) $meta['customer_asset_id']];
        }

        if ($ids === [] && $task->loanApplication && $task->task_type === 'asset_valuation') {
            $ids = app(CustomerAssetService::class)->onLoanAssetIds($task->loanApplication);
        }

        if ($ids === []) {
            return collect();
        }

        return CustomerAsset::query()->whereIn('id', $ids)->get();
    }

    /**
     * Pre-seeded engine / systems checks — valuers pick one, they do not type free text.
     *
     * @return array<string, string>
     */
    public function engineOptions(): array
    {
        return [
            'starts_smooth' => __('site.partner_portal.valuation_engine_starts_smooth'),
            'starts_rough' => __('site.partner_portal.valuation_engine_starts_rough'),
            'noise_smoke' => __('site.partner_portal.valuation_engine_noise_smoke'),
            'wont_start' => __('site.partner_portal.valuation_engine_wont_start'),
            'not_vehicle' => __('site.partner_portal.valuation_check_not_vehicle'),
        ];
    }

    /**
     * Pre-seeded test-drive outcomes.
     *
     * @return array<string, string>
     */
    public function driveOptions(): array
    {
        return [
            'drives_normal' => __('site.partner_portal.valuation_drive_normal'),
            'minor_issues' => __('site.partner_portal.valuation_drive_minor'),
            'pulls_or_vibration' => __('site.partner_portal.valuation_drive_pulls'),
            'unsafe' => __('site.partner_portal.valuation_drive_unsafe'),
            'not_vehicle' => __('site.partner_portal.valuation_check_not_vehicle'),
        ];
    }

    /** @return array<string, mixed> */
    public function payload(?ValuationAssignment $assignment): array
    {
        $raw = $assignment?->inspection;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CustomerAsset>  $assets
     * @return array<int, array<string, string>>
     */
    public function valuerPhotosByAsset(PartnerTask $task, $assets): array
    {
        $docs = $task->documents ?? collect();
        $out = [];
        foreach ($assets as $asset) {
            $angles = [];
            foreach (array_keys(CustomerAsset::photoAngleLabels($asset->asset_type)) as $angle) {
                $doc = $docs->first(function ($row) use ($asset, $angle) {
                    $type = (string) ($row->doc_type ?? '');

                    return $type === self::DOC_PREFIX.$angle.'_'.$asset->id
                        || $type === 'asset_photo_'.$angle.'_'.$asset->id
                        || $type === 'asset_photo_'.$angle;
                });
                if ($doc && filled($doc->file_path ?? $doc->path ?? null)) {
                    $angles[$angle] = (string) ($doc->file_path ?? $doc->path);
                }
            }
            $out[$asset->id] = $angles;
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CustomerAsset>  $assets
     * @return list<string>
     */
    public function missingValuerAngles(PartnerTask $task, $assets): array
    {
        $captured = $this->valuerPhotosByAsset($task, $assets);
        $missing = [];
        foreach ($assets as $asset) {
            foreach (array_keys(CustomerAsset::photoAngleLabels($asset->asset_type)) as $angle) {
                if (! filled($captured[$asset->id][$angle] ?? null)) {
                    $missing[] = $asset->label.' · '.(CustomerAsset::photoAngleLabels($asset->asset_type)[$angle] ?? $angle);
                }
            }
        }

        return $missing;
    }

    /**
     * One capture at a time — valuer photos only; owner uploads are never included.
     *
     * @param  \Illuminate\Support\Collection<int, CustomerAsset>  $assets
     * @return list<array{asset_id: int, asset_label: string, angle: string, label: string, path: ?string}>
     */
    public function photoSteps(PartnerTask $task, $assets): array
    {
        $captured = $this->valuerPhotosByAsset($task, $assets);
        $steps = [];
        foreach ($assets as $asset) {
            foreach (CustomerAsset::photoAngleLabels($asset->asset_type) as $angle => $label) {
                $steps[] = [
                    'asset_id' => $asset->id,
                    'asset_label' => (string) $asset->label,
                    'angle' => $angle,
                    'label' => $label,
                    'path' => $captured[$asset->id][$angle] ?? null,
                ];
            }
        }

        return $steps;
    }

    public function storePhoto(
        PartnerTask $task,
        int $partnerId,
        CustomerAsset $asset,
        string $angle,
        UploadedFile $file,
    ): PartnerDocument {
        $labels = CustomerAsset::photoAngleLabels($asset->asset_type);
        if (! isset($labels[$angle])) {
            throw ValidationException::withMessages([
                'angle' => __('site.partner_portal.valuation_unknown_angle'),
            ]);
        }

        $path = $file->store("partners/{$partnerId}/valuations/{$task->id}", 'public');
        $docType = self::DOC_PREFIX.$angle.'_'.$asset->id;

        $existing = PartnerDocument::query()
            ->where('partner_task_id', $task->id)
            ->when(
                Schema::hasColumn('partner_documents', 'doc_type') || Schema::hasColumn('vendor_documents', 'doc_type'),
                fn ($q) => $q->where('doc_type', $docType)
            )
            ->latest('id')
            ->first();

        $payload = [
            'vendor_id' => $partnerId,
            'vendor_task_id' => $task->id,
            'label' => $labels[$angle].' #'.$asset->id,
            'file_path' => $path,
            'mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ];
        if (Schema::hasColumn('partner_documents', 'doc_type') || Schema::hasColumn('vendor_documents', 'doc_type')) {
            $payload['doc_type'] = $docType;
        }

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return PartnerDocument::create($payload);
    }

    public function saveChecks(ValuationAssignment $assignment, array $data): ValuationAssignment
    {
        $payload = $this->payload($assignment);
        if (isset($data['engine'])) {
            $payload['engine'] = (string) $data['engine'];
        }
        if (isset($data['test_drive'])) {
            $payload['test_drive'] = (string) $data['test_drive'];
        }
        $assignment->update(['inspection' => $payload]);

        return $assignment->fresh();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CustomerAsset>  $assets
     */
    public function assertReadyToValue(PartnerTask $task, ValuationAssignment $assignment, $assets): void
    {
        if ($assets->isEmpty()) {
            throw ValidationException::withMessages([
                'photos' => [__('site.partner_portal.valuation_photos_required', ['list' => __('site.partner_portal.tab_asset')])],
            ]);
        }

        $missingPhotos = $this->missingValuerAngles($task, $assets);
        if ($missingPhotos !== []) {
            throw ValidationException::withMessages([
                'photos' => [__('site.partner_portal.valuation_photos_required', ['list' => implode(', ', $missingPhotos)])],
            ]);
        }

        $needsVehicleChecks = $assets->contains(fn (CustomerAsset $asset) => $asset->isVehicleLike());
        $payload = $this->payload($assignment);
        if ($needsVehicleChecks) {
            $engine = (string) ($payload['engine'] ?? '');
            $drive = (string) ($payload['test_drive'] ?? '');
            if ($engine === '' || ! array_key_exists($engine, $this->engineOptions())) {
                throw ValidationException::withMessages([
                    'engine' => [__('site.partner_portal.valuation_engine_required')],
                ]);
            }
            if ($drive === '' || ! array_key_exists($drive, $this->driveOptions())) {
                throw ValidationException::withMessages([
                    'test_drive' => [__('site.partner_portal.valuation_drive_required')],
                ]);
            }
        }
    }

    /**
     * @return array<int, array{market_value: float, forced_sale_value: float}>
     */
    public function parseValues(array $input, $assets): array
    {
        $perAsset = [];
        foreach ($assets as $asset) {
            $row = $input[(string) $asset->id] ?? $input[$asset->id] ?? [];
            $market = MoneyFormat::toNumber($row['market_value'] ?? 0);
            $fsv = MoneyFormat::toNumber($row['forced_sale_value'] ?? $row['fsv'] ?? 0);
            if ($market <= 0 || $fsv <= 0) {
                throw ValidationException::withMessages([
                    'values' => [__('site.partner_portal.valuation_values_required', ['asset' => $asset->label])],
                ]);
            }
            if ($fsv > $market) {
                throw ValidationException::withMessages([
                    'values' => [__('site.partner_portal.valuation_fsv_exceeds_market', ['asset' => $asset->label])],
                ]);
            }
            $perAsset[$asset->id] = [
                'market_value' => $market,
                'forced_sale_value' => $fsv,
            ];
        }

        return $perAsset;
    }

    public function checksSummary(ValuationAssignment $assignment): array
    {
        $payload = $this->payload($assignment);
        $engine = (string) ($payload['engine'] ?? '');
        $drive = (string) ($payload['test_drive'] ?? '');

        return [
            'engine' => $engine,
            'engine_label' => $this->engineOptions()[$engine] ?? null,
            'test_drive' => $drive,
            'drive_label' => $this->driveOptions()[$drive] ?? null,
        ];
    }
}
