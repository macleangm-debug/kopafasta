<?php

namespace App\Services;

use App\Models\CustomerPayment;
use App\Models\LenderTransaction;
use App\Models\PartnerPayment;
use App\Models\Repayment;

/**
 * Explains where an inbound CustomerPayment was (or will be) allocated —
 * microfinance ledger, capital partners, suppliers, affiliates.
 */
class PaymentFundDestinationService
{
    /**
     * @return list<array{party: string, role: string, amount: float, status: string, detail: string|null, url: string|null}>
     */
    public function destinations(CustomerPayment $payment): array
    {
        $payment->loadMissing(['loan', 'customer', 'journalEntry', 'source']);

        return match ($payment->payment_type) {
            'loan_repayment' => $this->forLoanRepayment($payment),
            'asset_deposit' => $this->forAssetDeposit($payment),
            'registration_fee', 'application_fee', 'post_approval_fee',
            'asset_reservation_fee', 'valuation_fee' => $this->forFee($payment),
            'insurance_premium' => [[
                'party' => brand_name().' / insurance partner',
                'role' => 'insurance_premium',
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'detail' => 'Premium collected (base + markup per insurance settings)',
                'url' => null,
            ]],
            default => [[
                'party' => brand_name(),
                'role' => 'collector',
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'detail' => $payment->typeLabel(),
                'url' => null,
            ]],
        };
    }

    /** @return list<array{party: string, role: string, amount: float, status: string, detail: string|null, url: string|null}> */
    private function forLoanRepayment(CustomerPayment $payment): array
    {
        $rows = [];
        $repayment = $payment->source instanceof Repayment
            ? $payment->source
            : Repayment::query()->where('reference', $payment->reference)->first();

        if ($repayment) {
            foreach ([
                'interest' => 'Interest — capital partners share from loan funding allocations',
                'principal' => 'Principal — reduces outstanding and partner exposure',
                'penalty' => 'Penalty / late fee income to '.brand_name(),
            ] as $field => $detail) {
                $amount = (float) ($repayment->{"{$field}_component"} ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $rows[] = [
                    'party' => $field === 'penalty' ? brand_name() : brand_name().' + capital partners',
                    'role' => $field,
                    'amount' => $amount,
                    'status' => $repayment->status,
                    'detail' => $detail,
                    'url' => $payment->loan_id ? route('admin.loans.show', $payment->loan_id) : null,
                ];
            }
        }

        $anchor = $payment->verified_at ?? $payment->paid_at ?? $payment->updated_at;
        if ($payment->loan_id && $anchor) {
            $lenderTx = LenderTransaction::query()
                ->with('lender')
                ->where('loan_id', $payment->loan_id)
                ->where('type', 'interest_earned')
                ->where('created_at', '>=', $anchor->copy()->subMinutes(2))
                ->where('created_at', '<=', $anchor->copy()->addMinutes(10))
                ->orderByDesc('id')
                ->get();

            foreach ($lenderTx as $tx) {
                $rows[] = [
                    'party' => $tx->lender?->name ?? 'Capital partner',
                    'role' => 'interest_earned',
                    'amount' => abs((float) $tx->amount),
                    'status' => $tx->status ?? 'completed',
                    'detail' => $tx->notes ?: 'Credited to capital partner wallet',
                    'url' => route('admin.lenders.adjust-capital', $tx->lender_id),
                ];
            }
        }

        if ($repayment) {
            foreach ($this->partnerPaymentsForSource(Repayment::class, (int) $repayment->id, 'managed_loan_repayment') as $row) {
                $rows[] = $row;
            }
        }

        if ($rows === []) {
            $rows[] = [
                'party' => brand_name(),
                'role' => 'loan_receivable',
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'detail' => 'Full amount applied to loan receivable on verification',
                'url' => $payment->loan_id ? route('admin.loans.show', $payment->loan_id) : null,
            ];
        }

        return $rows;
    }

    /** @return list<array{party: string, role: string, amount: float, status: string, detail: string|null, url: string|null}> */
    private function forAssetDeposit(CustomerPayment $payment): array
    {
        $rows = [[
            'party' => brand_name(),
            'role' => 'customer_deposit',
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'detail' => 'Customer deposit collected (may include markup over supplier price)',
            'url' => null,
        ]];

        foreach ($this->partnerPaymentsForSource(
            (string) ($payment->source_type ?? ''),
            (int) ($payment->source_id ?? 0),
            'supplier_deposit'
        ) as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return list<array{party: string, role: string, amount: float, status: string, detail: string|null, url: string|null}> */
    private function forFee(CustomerPayment $payment): array
    {
        $rows = [[
            'party' => brand_name(),
            'role' => 'fee_income',
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'detail' => $payment->typeLabel().' income',
            'url' => null,
        ]];

        $affiliate = PartnerPayment::query()
            ->with('partner')
            ->where('source_type', 'affiliate_commission')
            ->where(function ($q) use ($payment) {
                $q->where('reference', $payment->reference);
                if ($payment->source_type && $payment->source_id) {
                    $q->orWhere(function ($inner) use ($payment) {
                        $inner->where('source_type', $payment->source_type)
                            ->where('source_id', $payment->source_id);
                    });
                }
            })
            ->get();

        foreach ($affiliate as $vp) {
            $rows[] = [
                'party' => $vp->partner?->name ?? 'Affiliate',
                'role' => 'affiliate_commission',
                'amount' => (float) $vp->amount,
                'status' => $vp->status,
                'detail' => 'Affiliate commission accrued to partner wallet',
                'url' => route('admin.partner-payments.index'),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{party: string, role: string, amount: float, status: string, detail: string|null, url: string|null}>
     */
    private function partnerPaymentsForSource(string $sourceType, int $sourceId, string $paymentType): array
    {
        if ($sourceType === '' || $sourceId <= 0) {
            return [];
        }

        return PartnerPayment::query()
            ->with('partner')
            ->where('source_type', $paymentType)
            ->where(function ($q) use ($sourceType, $sourceId) {
                // Prefer matching the originating record when present; some rows only set category source_type.
                if ($sourceType !== '' && $sourceId > 0) {
                    $q->where('source_id', $sourceId);
                }
            })
            ->get()
            ->map(fn (PartnerPayment $vp) => [
                'party' => $vp->partner?->name ?? 'Partner',
                'role' => $paymentType,
                'amount' => (float) $vp->amount,
                'status' => $vp->status,
                'detail' => match ($paymentType) {
                    'supplier_deposit' => 'Supplier quotation amount accrued to partner wallet',
                    'managed_loan_repayment' => 'Supplier / managed-asset principal share',
                    default => $paymentType,
                },
                'url' => route('admin.partner-payments.index'),
            ])
            ->all();
    }
}
