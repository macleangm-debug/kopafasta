<?php

namespace App\Services;

use App\Models\Lender;
use App\Models\LenderTransaction;
use Illuminate\Support\Collection;

class CapitalPartnerLedgerService
{
    /** @var array<string, string> */
    public const CREDIT_TYPES = [
        'deposit'          => 'Capital deposit',
        'return'           => 'Capital return',
        'interest_earned'  => 'Interest earned',
        'profit_adjustment'=> 'Profit adjustment',
    ];

    /** @var array<string, string> */
    public const DEBIT_TYPES = [
        'investment'       => 'Loan allocation',
        'withdrawal'       => 'Withdrawal',
        'loss_allocation'  => 'Loss allocation',
        'admin_adjustment' => 'Administrative adjustment',
        'fee'              => 'Fee',
    ];

    /**
     * @return array{entries: list<array<string, mixed>>, closing: array<string, float>}
     */
    public function forLender(Lender $lender, int $limit = 200): array
    {
        $metrics = app(CapitalPartnerMetricsService::class)->forLender($lender);

        $transactions = LenderTransaction::query()
            ->where('lender_id', $lender->id)
            ->where('status', 'completed')
            ->with(['loan', 'pool', 'createdBy'])
            ->orderBy('processed_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $available = 0.0;
        $inUse = 0.0;
        $outstanding = 0.0;
        $earnings = 0.0;
        $entries = [];

        foreach ($transactions as $txn) {
            $direction = $txn->direction ?? $this->inferDirection($txn->type);
            $credit = $direction === 'credit' ? (float) $txn->amount : 0.0;
            $debit = $direction === 'debit' ? (float) $txn->amount : 0.0;

            if ($txn->type === 'deposit' || $txn->type === 'return' || $txn->type === 'profit_adjustment') {
                $available += $credit;
            }
            if ($txn->type === 'investment') {
                $available -= $debit;
                $inUse += $debit;
                $outstanding += $debit;
            }
            if ($txn->type === 'interest_earned') {
                $earnings += $credit;
                $available += $credit;
            }
            if (in_array($txn->type, ['withdrawal', 'admin_adjustment'], true)) {
                $available -= $debit;
            }
            if ($txn->type === 'return' && $debit > 0) {
                $inUse = max(0, $inUse - $debit);
                $outstanding = max(0, $outstanding - $debit);
            }

            $entries[] = [
                'at'                  => $txn->processed_at ?? $txn->created_at,
                'reference'           => $txn->reference,
                'type'                => $txn->type,
                'category'            => self::CREDIT_TYPES[$txn->type] ?? self::DEBIT_TYPES[$txn->type] ?? ucfirst(str_replace('_', ' ', $txn->type)),
                'description'         => $txn->notes ?: ($txn->loan?->loan_number ? 'Loan '.$txn->loan->loan_number : '—'),
                'credit'              => $credit,
                'debit'               => $debit,
                'available_capital'   => $available,
                'capital_in_use'      => $inUse,
                'outstanding_balance' => $outstanding,
                'total_earnings'      => $earnings,
                'loan_number'         => $txn->loan?->loan_number,
                'actor'               => $txn->createdBy?->name,
            ];
        }

        return [
            'entries' => array_reverse($entries),
            'closing' => [
                'available_capital'   => $metrics['capital_available'],
                'capital_in_use'      => $metrics['capital_utilized'],
                'outstanding_balance' => $metrics['outstanding_exposure'],
                'total_earnings'      => $metrics['interest_earned_partner'],
            ],
        ];
    }

    public function inferDirection(string $type): string
    {
        if (array_key_exists($type, self::CREDIT_TYPES)) {
            return 'credit';
        }

        return 'debit';
    }

    /** @return Collection<int, \App\Models\LenderTransaction> */
    public function fundingHistory(Lender $lender, int $limit = 50): Collection
    {
        return LenderTransaction::query()
            ->where('lender_id', $lender->id)
            ->whereIn('type', ['deposit', 'withdrawal', 'admin_adjustment', 'return'])
            ->latest('processed_at')
            ->limit($limit)
            ->get();
    }
}
