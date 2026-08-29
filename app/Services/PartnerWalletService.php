<?php

namespace App\Services;

use App\Models\PartnerPayment;
use App\Models\Vendor;

class PartnerWalletService
{
    /**
     * Wallet source used for service-partner payouts (not recovery/affiliate).
     */
    public function sourceTypeFor(Vendor $vendor): string
    {
        if ($vendor->isInsurance()) {
            return 'insurance_premium';
        }

        if (app(RecoveryPartnerService::class)->isRecoveryPartner($vendor)) {
            return RecoveryCommissionWalletService::SOURCE_TYPE;
        }

        if ($vendor->isValuer()) {
            return 'valuation_fee';
        }

        return 'vendor_task';
    }

    /**
     * @return array{
     *   source_type: string,
     *   available: float,
     *   pending: float,
     *   approved: float,
     *   paid: float,
     *   counts: array{pending: int, approved: int, paid: int}
     * }
     */
    public function summary(Vendor $vendor, ?string $sourceType = null): array
    {
        $sourceType ??= $this->sourceTypeFor($vendor);

        if ($sourceType === 'valuation_fee') {
            app(PartnerSettlementService::class)->promotePendingValuationFees($vendor);
        }

        $base = PartnerPayment::query()
            ->where('partner_id', $vendor->id)
            ->where('source_type', $sourceType);

        $pending = (clone $base)->where('status', 'pending')->sum('amount');
        $approved = (clone $base)->where('status', 'approved')->sum('amount');
        $paid = (clone $base)->where('status', 'paid')->sum('amount');

        return [
            'source_type' => $sourceType,
            'available'   => app(PartnerPayoutRequestService::class)->availableBalance($vendor, $sourceType),
            'pending'     => (float) $pending,
            'approved'    => (float) $approved,
            'paid'        => (float) $paid,
            'counts'      => [
                'pending'  => (clone $base)->where('status', 'pending')->count(),
                'approved' => (clone $base)->where('status', 'approved')->count(),
                'paid'     => (clone $base)->where('status', 'paid')->count(),
            ],
        ];
    }
}
