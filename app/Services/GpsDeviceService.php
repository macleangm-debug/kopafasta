<?php

namespace App\Services;

use App\Models\CustomerAsset;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\PartnerTask;
use App\Models\Setting;
use App\Models\Vendor;

class GpsDeviceService
{
    /** Platform-wide: show View Asset Location links when a per-device tracking URL exists. */
    public function mapEnabled(): bool
    {
        return filter_var(Setting::get('gps.map_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string, string> */
    public function providerOptions(): array
    {
        return collect(config('gps.providers', []))
            ->mapWithKeys(fn (array $meta, string $key) => [$key => (string) ($meta['label'] ?? $key)])
            ->all();
    }

    public function defaultProvider(): string
    {
        $default = (string) config('gps.default_provider', 'generic');

        return isset(config('gps.providers')[$default]) ? $default : 'generic';
    }

    /**
     * Persist install details on the task and onto loan collateral / marketplace assets.
     * Each device gets its own tracking URL, tied to the loan application.
     *
     * @param  array{gps_serial?: ?string, gps_provider?: ?string, gps_device_id?: ?string, gps_tracking_url?: ?string}  $data
     */
    public function recordInstallFromTask(PartnerTask $task, array $data): PartnerTask
    {
        $serial = trim((string) ($data['gps_serial'] ?? $task->gps_serial ?? ''));
        $provider = trim((string) ($data['gps_provider'] ?? $task->gps_provider ?? $this->defaultProvider()));
        $deviceId = trim((string) ($data['gps_device_id'] ?? $task->gps_device_id ?? ''));
        $trackingUrl = trim((string) ($data['gps_tracking_url'] ?? $task->gps_tracking_url ?? ''));

        if ($provider === '' || ! isset(config('gps.providers')[$provider])) {
            $provider = $this->defaultProvider();
        }

        $task->update([
            'gps_serial' => $serial !== '' ? $serial : $task->gps_serial,
            'gps_provider' => $provider,
            'gps_device_id' => $deviceId !== '' ? $deviceId : null,
            'gps_tracking_url' => $trackingUrl !== '' ? $trackingUrl : null,
        ]);

        $task = $task->fresh();
        $this->syncToCollateralAssets($task);
        app(AssetReservationService::class)->syncGpsFromTask($task);

        return $task;
    }

    public function syncToCollateralAssets(PartnerTask $task): void
    {
        if (! $task->loan_application_id) {
            return;
        }

        $payload = [
            'gps_serial' => $task->gps_serial,
            'gps_provider' => $task->gps_provider,
            'gps_device_id' => $task->gps_device_id,
            'tracking_url' => $task->gps_tracking_url,
            'gps_tracking_url' => $task->gps_tracking_url,
            'gps_installed_at' => now()->toIso8601String(),
            'gps_install_task_id' => $task->id,
            'gps_installer_partner_id' => $task->partner_id ?? $task->vendor_id ?? null,
        ];

        $rows = LoanApplicationAsset::query()
            ->with('customerAsset')
            ->where('loan_application_id', $task->loan_application_id)
            ->where(function ($q) {
                $q->where('gps_required', true)->orWhere('is_primary', true);
            })
            ->get();

        if ($rows->isEmpty()) {
            $rows = LoanApplicationAsset::query()
                ->with('customerAsset')
                ->where('loan_application_id', $task->loan_application_id)
                ->orderByDesc('is_primary')
                ->limit(1)
                ->get();
        }

        foreach ($rows as $row) {
            $asset = $row->customerAsset;
            if (! $asset) {
                continue;
            }
            $this->mergeAssetGpsMetadata($asset, $payload);
        }
    }

    /** @param  array<string, mixed>  $gps */
    public function mergeAssetGpsMetadata(CustomerAsset $asset, array $gps): void
    {
        $meta = (array) ($asset->metadata ?? []);
        foreach ($gps as $key => $value) {
            if ($value !== null && $value !== '') {
                $meta[$key] = $value;
            }
        }
        $asset->update(['metadata' => $meta]);
    }

    /**
     * GPS installer who completed install for this loan — for debt collector deactivation contact.
     *
     * @return array{name: ?string, phone: ?string, email: ?string, partner_id: ?int}|null
     */
    public function installerContactForLoan(?Loan $loan): ?array
    {
        if (! $loan?->loan_application_id) {
            return null;
        }

        $task = PartnerTask::query()
            ->with('partner')
            ->where('loan_application_id', $loan->loan_application_id)
            ->where(function ($q) {
                $q->where('task_type', 'gps_install')
                    ->orWhere('task_type', 'like', '%gps%');
            })
            ->where('status', 'completed')
            ->latest('completed_at')
            ->latest('id')
            ->first();

        if (! $task) {
            return null;
        }

        /** @var Vendor|null $partner */
        $partner = $task->partner;
        if (! $partner && filled($task->partner_id)) {
            $partner = Vendor::query()->find($task->partner_id);
        }

        if (! $partner) {
            return null;
        }

        return [
            'name' => $partner->name,
            'phone' => $partner->phone,
            'email' => $partner->email,
            'partner_id' => $partner->id,
        ];
    }

    /**
     * Collateral + GPS summary for credit management and recovery partners.
     *
     * @return list<array<string, mixed>>
     */
    public function collateralForLoan(?Loan $loan): array
    {
        if (! $loan?->loan_application_id) {
            return [];
        }

        $loan->loadMissing(['application']);

        return $loan->application ? $this->forApplication($loan->application) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forApplication(LoanApplication $application): array
    {
        $application->loadMissing(['collateralAssets.customerAsset.customer']);

        $gpsTasks = PartnerTask::query()
            ->where('loan_application_id', $application->id)
            ->where(function ($q) {
                $q->where('task_type', 'gps_install')
                    ->orWhere('task_type', 'like', '%gps%');
            })
            ->latest('id')
            ->get();

        $mapEnabled = $this->mapEnabled();
        $items = [];

        foreach ($application->collateralAssets as $row) {
            $asset = $row->customerAsset;
            $meta = (array) ($asset?->metadata ?? []);
            $details = (array) ($meta['details'] ?? []);
            $gpsTask = $gpsTasks->first(fn (PartnerTask $t) => $t->status === 'completed')
                ?? $gpsTasks->first();

            $serial = $gpsTask?->gps_serial
                ?? ($meta['gps_serial'] ?? null)
                ?? ($meta['device_serial'] ?? null)
                ?? ($details['serial_number'] ?? null);

            $provider = $gpsTask?->gps_provider ?? ($meta['gps_provider'] ?? null);
            $deviceId = $gpsTask?->gps_device_id ?? ($meta['gps_device_id'] ?? null);
            $trackingUrl = $gpsTask?->gps_tracking_url
                ?? ($meta['tracking_url'] ?? null)
                ?? ($meta['gps_tracking_url'] ?? null);

            $gpsStatus = 'not_required';
            if ($row->gps_required) {
                if (filled($serial) && ($gpsTask?->status === 'completed' || filled($meta['gps_installed_at'] ?? null))) {
                    $gpsStatus = 'secured';
                } elseif ($gpsTask && in_array($gpsTask->status, ['assigned', 'accepted', 'in_progress'], true)) {
                    $gpsStatus = 'install_pending';
                } else {
                    $gpsStatus = 'required';
                }
            } elseif (filled($serial)) {
                $gpsStatus = 'secured';
            }

            $card = $asset
                ? $asset->toCollateralCard([
                    'belongs_to' => $asset->customer?->full_name,
                ])
                : [
                    'label' => $row->description ?: display_label($row->asset_type, 'asset_type'),
                    'asset_type' => $row->asset_type,
                    'type_label' => \App\Models\CustomerAsset::typeOptions()[$row->asset_type]
                        ?? display_label($row->asset_type, 'asset_type'),
                    'registration_number' => $details['registration_number'] ?? null,
                    'make' => $details['make'] ?? null,
                    'year' => $details['year'] ?? $details['purchase_year'] ?? null,
                    'chassis' => null,
                    'belongs_to' => null,
                    'status_label' => null,
                    'thumbnail' => null,
                    'insurance_type' => null,
                    'insurance_expires_at' => null,
                    'insurance_policy_number' => null,
                    'has_insurance_doc' => false,
                ];

            $items[] = [
                'label' => $asset?->label
                    ?: ($row->description ?: display_label($row->asset_type, 'asset_type')),
                'asset_type' => $row->asset_type,
                'registration_number' => $asset?->registration_number
                    ?? ($details['registration_number'] ?? null),
                'gps_required' => (bool) $row->gps_required,
                'gps_status' => $gpsStatus,
                'gps_serial' => $serial,
                'gps_provider' => $provider,
                'gps_device_id' => $deviceId,
                'tracking_url' => filled($trackingUrl) ? (string) $trackingUrl : null,
                'can_view_asset' => $mapEnabled && filled($trackingUrl),
                'is_primary' => (bool) $row->is_primary,
                'card' => $card,
            ];
        }

        return $items;
    }
}
