<?php

namespace App\Services;

use App\Models\PartnerSettlement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerSettlementService
{
    public function accrue(
        Vendor $vendor,
        int $amount,
        string $sourceType,
        ?int $sourceId = null,
        ?string $description = null,
        ?int $vendorTaskId = null,
    ): VendorPayment {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Settlement amount must be positive.');
        }

        $payment = VendorPayment::create([
            'vendor_id'       => $vendor->id,
            'vendor_task_id'  => $vendorTaskId,
            'invoice_number'  => 'INV-'.strtoupper(Str::random(8)),
            'amount'          => $amount,
            'status'          => 'pending',
            'source_type'     => $sourceType,
            'source_id'       => $sourceId,
            'description'     => $description,
        ]);

        if ($this->shouldAutoApprove($vendor, $sourceType, $amount)) {
            $this->approvePayment($payment, $this->systemUser());
        }

        return $payment;
    }

    private function shouldAutoApprove(Vendor $vendor, string $sourceType, int $amount): bool
    {
        if ($vendor->status !== 'active') {
            return false;
        }

        $max = (int) config('partner_settlements.auto_approve_max_amount', 500_000);
        $types = config('partner_settlements.auto_approve_source_types', ['supplier_deposit']);

        return in_array($sourceType, $types, true) && $amount > 0 && $amount <= $max;
    }

    private function systemUser(): User
    {
        return User::query()->where('role', 'admin')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->firstOrFail();
    }

    public function approvePayment(VendorPayment $payment, User $user): VendorPayment
    {
        if ($payment->status !== 'pending') {
            throw new \InvalidArgumentException('Only pending payments can be approved.');
        }

        $payment->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        return $payment->refresh();
    }

    public function cancelPayment(VendorPayment $payment, ?string $notes = null): VendorPayment
    {
        if (in_array($payment->status, ['paid', 'cancelled'], true)) {
            throw new \InvalidArgumentException('This payment cannot be cancelled.');
        }

        $payment->update([
            'status' => 'cancelled',
            'notes'  => trim(($payment->notes ?? '').($notes ? "\nCancelled: {$notes}" : '')),
        ]);

        return $payment->refresh();
    }

    /** Group approved, unbatched vendor payments into weekly settlement batches. */
    public function queueWeeklySettlements(?CarbonImmutable $periodEnd = null): int
    {
        $periodEnd ??= CarbonImmutable::today();
        $periodStart = $periodEnd->subDays(6);
        $created = 0;

        $vendorIds = VendorPayment::query()
            ->where('status', 'approved')
            ->whereNull('partner_settlement_id')
            ->distinct()
            ->pluck('partner_id');

        foreach ($vendorIds as $vendorId) {
            $payments = VendorPayment::query()
                ->where('partner_id', $vendorId)
                ->where('status', 'approved')
                ->whereNull('partner_settlement_id')
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty()) {
                continue;
            }

            DB::transaction(function () use ($payments, $vendorId, $periodStart, $periodEnd, &$created): void {
                $settlement = PartnerSettlement::create([
                    'vendor_id'    => $vendorId,
                    'reference'    => 'PS-'.strtoupper(Str::random(8)),
                    'period_start' => $periodStart->toDateString(),
                    'period_end'   => $periodEnd->toDateString(),
                    'total_amount' => (int) $payments->sum('amount'),
                    'status'       => 'pending',
                ]);

                VendorPayment::query()
                    ->whereIn('id', $payments->pluck('id'))
                    ->update(['partner_settlement_id' => $settlement->id]);

                $created++;
            });
        }

        return $created;
    }

    public function approveSettlement(PartnerSettlement $settlement, User $user): PartnerSettlement
    {
        if ($settlement->status !== 'pending') {
            throw new \InvalidArgumentException('Only pending settlement batches can be approved.');
        }

        $settlement->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        return $settlement->refresh();
    }

    public function markSettlementPaid(
        PartnerSettlement $settlement,
        User $user,
        ?string $channel = null,
        ?string $reference = null,
        ?string $notes = null,
    ): PartnerSettlement {
        if (! in_array($settlement->status, ['pending', 'approved'], true)) {
            throw new \InvalidArgumentException('This settlement batch cannot be marked paid.');
        }

        return DB::transaction(function () use ($settlement, $user, $channel, $reference, $notes): PartnerSettlement {
            $settlement->update([
                'status'             => 'paid',
                'approved_at'        => $settlement->approved_at ?? now(),
                'approved_by'        => $settlement->approved_by ?? $user->id,
                'paid_at'            => now(),
                'channel'            => $channel,
                'payment_reference'  => $reference,
                'notes'              => $notes ? trim(($settlement->notes ?? '')."\nPaid: {$notes}") : $settlement->notes,
            ]);

            VendorPayment::query()
                ->where('partner_settlement_id', $settlement->id)
                ->whereIn('status', ['approved', 'pending'])
                ->update([
                    'status'  => 'paid',
                    'paid_at' => now(),
                    'channel' => $channel,
                    'reference' => $reference,
                ]);

            return $settlement->refresh();
        });
    }
}
