<?php

namespace App\Services;

use App\Models\PartnerPayment;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AffiliateCommissionWalletService
{
    public const SOURCE_TYPE = 'affiliate_commission';

    /** @return array{pending: int, approved: int, paid: int, disputed: int, counts: array<string, int>} */
    public function summary(Vendor $vendor): array
    {
        $base = $this->query($vendor);

        $amounts = [];
        $counts = [];

        foreach (['pending', 'approved', 'paid', 'disputed'] as $status) {
            $amounts[$status] = (int) (clone $base)->where('status', $status)->sum('amount');
            $counts[$status] = (int) (clone $base)->where('status', $status)->count();
        }

        return array_merge($amounts, ['counts' => $counts]);
    }

    public function query(Vendor $vendor): Builder
    {
        return PartnerPayment::query()
            ->where('partner_id', $vendor->id)
            ->where('source_type', self::SOURCE_TYPE);
    }

    public function paginated(Vendor $vendor, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($vendor)
            ->latest()
            ->paginate($perPage);
    }

    public function dispute(PartnerPayment $payment, Vendor $vendor, string $reason): PartnerPayment
    {
        if ((int) $payment->partner_id !== (int) $vendor->id) {
            throw new \InvalidArgumentException('You do not own this payment.');
        }

        if ($payment->source_type !== self::SOURCE_TYPE) {
            throw new \InvalidArgumentException('Only affiliate commission payments can be disputed.');
        }

        if (! in_array($payment->status, ['pending', 'approved'], true)) {
            throw new \InvalidArgumentException('Only pending or approved payments can be disputed.');
        }

        $payment->update([
            'status'         => 'disputed',
            'dispute_reason' => trim($reason),
            'disputed_at'    => now(),
        ]);

        return $payment->refresh();
    }
}
