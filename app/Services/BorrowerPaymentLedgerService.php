<?php

namespace App\Services;

use App\Models\BorrowerRefund;
use App\Models\Customer;
use App\Models\CustomerPayment;
use Illuminate\Support\Collection;

class BorrowerPaymentLedgerService
{
    /** @return Collection<int, array<string, mixed>> */
    public function entriesFor(Customer $customer, int $limit = 50): Collection
    {
        $payments = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (CustomerPayment $payment) => [
                'kind'       => 'payment',
                'id'         => $payment->id,
                'date'       => $payment->created_at,
                'reference'  => $payment->reference,
                'type_label' => $payment->typeLabel(),
                'amount'     => (float) $payment->amount,
                'status'     => $payment->status,
                'status_label' => $payment->statusLabel(),
                'url'        => route('site.borrower.payments.show', $payment),
            ]);

        $refunds = BorrowerRefund::query()
            ->where('customer_id', $customer->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (BorrowerRefund $refund) => [
                'kind'       => 'refund',
                'id'         => $refund->id,
                'date'       => $refund->created_at,
                'reference'  => $refund->reference,
                'type_label' => __('borrower.payments_page.refund.title'),
                'amount'     => (float) $refund->amount,
                'status'     => $refund->status,
                'status_label' => __('borrower.payments_page.refund.statuses.'.$refund->status)
                    ?: str_replace('_', ' ', ucfirst($refund->status)),
                'url'        => route('site.borrower.payments.refund', $refund),
            ]);

        return collect($payments)->merge($refunds)
            ->sortByDesc(fn (array $row) => $row['date']?->timestamp ?? 0)
            ->values()
            ->take($limit);
    }
}
