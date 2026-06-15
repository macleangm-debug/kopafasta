<?php

namespace App\Services;

use App\Models\AssetReservation;
use App\Models\Vendor;

class UpfrontSettlementService
{
    public function isUpfrontSupplier(?Vendor $vendor): bool
    {
        return $vendor && app(AssetLendingService::class)->supplierType($vendor) === 'upfront_settlement';
    }

    public function settlementAmount(AssetReservation $reservation): float
    {
        $reservation->loadMissing('asset');
        $assetValue = (float) ($reservation->asset?->asset_value ?? 0);
        $depositPaid = (float) ($reservation->deposit_amount ?? 0);

        return max(0, round($assetValue - $depositPaid, 2));
    }

    public function accrueIfNeeded(AssetReservation $reservation, string $trigger = 'approval'): void
    {
        $reservation->loadMissing('asset.vendor');
        $vendor = $reservation->asset?->vendor;

        if (! $this->isUpfrontSupplier($vendor)) {
            return;
        }

        if (! in_array($reservation->deposit_status, ['paid'], true) && ! $reservation->deposit_paid_at) {
            return;
        }

        $amount = (int) round($this->settlementAmount($reservation));
        if ($amount <= 0) {
            return;
        }

        $exists = \App\Models\VendorPayment::query()
            ->where('vendor_id', $vendor->id)
            ->where('source_type', 'upfront_settlement')
            ->where('source_id', $reservation->id)
            ->exists();

        if ($exists) {
            return;
        }

        app(PartnerSettlementService::class)->accrue(
            $vendor,
            $amount,
            'upfront_settlement',
            $reservation->id,
            'Upfront supplier settlement ('.$trigger.') for '.($reservation->asset?->title ?? 'asset'),
        );
    }
}
