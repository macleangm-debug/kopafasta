<?php

namespace App\Services;

use App\Models\AffiliateEvent;
use App\Models\CustomerPayment;
use App\Models\PartnerPayment;
use App\Models\PartnerPayoutRequest;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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

    public function paginatedLedger(Vendor $vendor, float $reserved = 0, int $perPage = 10): LengthAwarePaginator
    {
        $this->promoteVerifiedCommissions($vendor);
        $reservedIds = $this->reservedRowIds($vendor, $reserved);
        $page = $this->query($vendor)->latest('id')->paginate($perPage);
        $page->getCollection()->transform(fn (PartnerPayment $row) => $this->mapLedgerRow($row, $reservedIds));

        return $page;
    }

    /**
     * Ledger rows for the Affiliate wallet. Does not create commissions.
     *
     * @return list<array<string, mixed>>
     */
    public function ledgerRows(Vendor $vendor, float $reserved = 0): array
    {
        $this->promoteVerifiedCommissions($vendor);
        $reservedIds = $this->reservedRowIds($vendor, $reserved);

        return $this->query($vendor)->latest('id')->get()
            ->map(fn (PartnerPayment $row) => $this->mapLedgerRow($row, $reservedIds))
            ->all();
    }

    /**
     * @return list<int>
     */
    private function reservedRowIds(Vendor $vendor, float $reserved): array
    {
        $remaining = max(0, $reserved);
        $ids = [];
        foreach ($this->query($vendor)->where('status', 'approved')->latest('id')->get() as $row) {
            if ($remaining <= 0) {
                break;
            }
            $ids[] = (int) $row->id;
            $remaining = max(0, $remaining - (float) $row->amount);
        }

        return $ids;
    }

    /**
     * @param  list<int>  $reservedIds
     * @return array<string, mixed>
     */
    private function mapLedgerRow(PartnerPayment $row, array $reservedIds): array
    {
        $this->attachCustomerPaymentReference($row);
        $customerPayment = $this->customerPaymentFor($row);
        $event = $row->source_id
            ? AffiliateEvent::query()->find($row->source_id)
            : null;
        $feeType = $this->feeTypeFromEvent($event);
        $status = (string) $row->status;
        $displayStatus = match ($status) {
            'approved' => in_array((int) $row->id, $reservedIds, true) ? 'reserved' : 'earned',
            'complete' => 'earned',
            default => $status,
        };

        $meta = is_array($row->meta) ? $row->meta : [];

        return [
            'id' => $row->id,
            'payment_id' => $customerPayment?->reference ?: '—',
            'date' => $customerPayment?->paid_at ?: $row->created_at,
            'member_no' => $customerPayment?->customer?->member_no
                ?: $event?->customer?->member_no
                ?: '—',
            'paid_for' => $customerPayment?->typeLabel()
                ?: $this->obligationLabel($feeType),
            'payment_amount' => $customerPayment
                ? CustomerPaymentService::collectableAmount($customerPayment)
                : null,
            'commission_base' => isset($meta['commission_base']) ? (float) $meta['commission_base'] : null,
            'commission_rate_percent' => isset($meta['commission_rate_percent']) ? (float) $meta['commission_rate_percent'] : null,
            'calculation_base' => $meta['calculation_base'] ?? null,
            'commission' => (float) $row->amount,
            'status' => $displayStatus,
            'domain_status' => $status,
            'qualifying_payment_reference' => $meta['qualifying_payment_reference']
                ?? ($customerPayment?->reference ?: null),
        ];
    }

    public function promoteVerifiedCommissions(Vendor $vendor): int
    {
        return app(PartnerSettlementService::class)->promotePendingAffiliateCommissions($vendor);
    }

    /**
     * @return \Illuminate\Support\Collection<int, PartnerPayoutRequest>
     */
    public function withdrawals(Vendor $vendor): Collection
    {
        return PartnerPayoutRequest::query()
            ->where('partner_id', $vendor->id)
            ->where(function ($query): void {
                $column = Schema::hasColumn('partner_payout_requests', 'source_type')
                    ? 'source_type'
                    : 'wallet_type';
                $query->where($column, self::SOURCE_TYPE);
            })
            ->latest('id')
            ->get();
    }

    public function reservedAmount(Vendor $vendor): float
    {
        return (float) PartnerPayoutRequest::query()
            ->where('partner_id', $vendor->id)
            ->whereIn('status', ['pending', 'approved'])
            ->when(
                Schema::hasColumn('partner_payout_requests', 'source_type'),
                fn ($q) => $q->where('source_type', self::SOURCE_TYPE),
                fn ($q) => $q->where('wallet_type', self::SOURCE_TYPE),
            )
            ->sum('amount');
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

        $fresh = $payment->refresh();
        app(PartnerSettlementService::class)->reverseAffiliateCommissionEarnedJournal($fresh);

        return $fresh;
    }

    private function attachCustomerPaymentReference(PartnerPayment $row): void
    {
        if (str_starts_with((string) $row->reference, 'PAY-')) {
            return;
        }

        $payment = $this->inferCustomerPayment($row);
        if (! $payment?->reference) {
            return;
        }

        $already = PartnerPayment::query()
            ->where('source_type', self::SOURCE_TYPE)
            ->where('reference', $payment->reference)
            ->whereKeyNot($row->id)
            ->exists();
        if ($already) {
            return;
        }

        $row->update(['reference' => $payment->reference]);
        $event = $row->source_id ? AffiliateEvent::query()->find($row->source_id) : null;
        if ($event && blank($event->landing_page)) {
            $event->update(['landing_page' => 'payment:'.$payment->id]);
        }
    }

    private function customerPaymentFor(PartnerPayment $row): ?CustomerPayment
    {
        if (str_starts_with((string) $row->reference, 'PAY-')) {
            return CustomerPayment::query()
                ->with('customer')
                ->where('reference', $row->reference)
                ->first();
        }

        return $this->inferCustomerPayment($row);
    }

    private function inferCustomerPayment(PartnerPayment $row): ?CustomerPayment
    {
        $event = $row->source_id ? AffiliateEvent::query()->find($row->source_id) : null;
        if (! $event?->customer_id) {
            return null;
        }

        $feeType = $this->feeTypeFromEvent($event);
        if ($feeType === '') {
            return null;
        }

        if (is_string($event->landing_page) && str_starts_with($event->landing_page, 'payment:')) {
            $id = (int) substr($event->landing_page, 8);
            if ($id > 0) {
                return CustomerPayment::query()->with('customer')->find($id);
            }
        }

        return CustomerPayment::query()
            ->with('customer')
            ->where('customer_id', $event->customer_id)
            ->where('payment_type', $feeType)
            ->whereIn('status', ['paid', 'verified'])
            ->latest('id')
            ->first();
    }

    private function feeTypeFromEvent(?AffiliateEvent $event): string
    {
        $type = (string) ($event?->event_type ?? '');
        if (! str_starts_with($type, 'commission_')) {
            return '';
        }

        return CustomerPayment::canonicalType(substr($type, 11));
    }

    private function obligationLabel(string $feeType): string
    {
        if ($feeType === '') {
            return __('site.affiliate_portal.commission_payment');
        }

        $payment = new CustomerPayment(['payment_type' => $feeType]);

        return $payment->typeLabel();
    }
}
