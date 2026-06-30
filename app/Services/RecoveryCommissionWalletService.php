<?php

namespace App\Services;

use App\Models\PartnerPayment;
use App\Models\RecoveryAssignment;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class RecoveryCommissionWalletService
{
    public const SOURCE_TYPE = 'recovery_commission';

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
            ->with('task')
            ->latest()
            ->paginate($perPage);
    }

    public function dispute(PartnerPayment $payment, Vendor $vendor, string $reason): PartnerPayment
    {
        if ((int) $payment->partner_id !== (int) $vendor->id) {
            throw new \InvalidArgumentException('You do not own this payment.');
        }

        if ($payment->source_type !== self::SOURCE_TYPE) {
            throw new \InvalidArgumentException('Only recovery commission payments can be disputed.');
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

    public function syncAssignmentCommissionPaid(PartnerPayment $payment): void
    {
        if ($payment->source_type !== self::SOURCE_TYPE || $payment->status !== 'paid' || ! $payment->source_id) {
            return;
        }

        $assignment = RecoveryAssignment::find($payment->source_id);
        if (! $assignment) {
            return;
        }

        $paid = (float) PartnerPayment::query()
            ->where('source_type', self::SOURCE_TYPE)
            ->where('source_id', $assignment->id)
            ->where('status', 'paid')
            ->sum('amount');

        $assignment->update([
            'commission_paid' => min((float) $assignment->commission_earned, $paid),
        ]);
    }
}
