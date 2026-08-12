<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GeneralLedgerService
{
    public function signedBalance(ChartOfAccount $account, ?Carbon $asOf = null): float
    {
        $opening = (float) $account->opening_balance;

        $query = JournalEntryLine::query()
            ->where('chart_of_account_id', $account->id)
            ->whereHas('entry', function ($q) use ($asOf) {
                $q->where('status', 'posted');
                if ($asOf) {
                    $q->where('entry_date', '<=', $asOf->toDateString());
                }
            });

        $debits = (float) (clone $query)->sum('debit');
        $credits = (float) (clone $query)->sum('credit');

        if (in_array($account->type, ['asset', 'expense'], true)) {
            return round($opening + $debits - $credits, 2);
        }

        return round($opening + $credits - $debits, 2);
    }

    /** @return Collection<int, object{code: string, name: string, type: string, debit: float, credit: float, balance: float}> */
    public function trialBalanceRows(?Carbon $asOf = null): Collection
    {
        return ChartOfAccount::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function (ChartOfAccount $account) use ($asOf) {
                $balance = $this->signedBalance($account, $asOf);
                $debitNormal = in_array($account->type, ['asset', 'expense'], true);

                return (object) [
                    'code'    => $account->code,
                    'name'    => $account->name,
                    'type'    => $account->type,
                    'balance' => $balance,
                    'debit'   => $debitNormal ? max($balance, 0) : max(-$balance, 0),
                    'credit'  => $debitNormal ? max(-$balance, 0) : max($balance, 0),
                ];
            })
            ->filter(fn ($row) => abs($row->balance) >= 0.01 || abs($row->debit) >= 0.01 || abs($row->credit) >= 0.01);
    }

    /** @return array<string, float> */
    public function balancesByType(?Carbon $asOf = null): array
    {
        $totals = [
            'asset'     => 0.0,
            'liability' => 0.0,
            'equity'    => 0.0,
            'income'    => 0.0,
            'expense'   => 0.0,
        ];

        foreach (ChartOfAccount::query()->where('is_active', true)->get() as $account) {
            $totals[$account->type] = ($totals[$account->type] ?? 0) + $this->signedBalance($account, $asOf);
        }

        return array_map(fn ($v) => round((float) $v, 2), $totals);
    }

    public function accountBalanceByCode(string $code, ?Carbon $asOf = null): float
    {
        $account = ChartOfAccount::query()->where('code', $code)->first();

        return $account ? $this->signedBalance($account, $asOf) : 0.0;
    }

    /**
     * Period activity by account type (income / expense), signed as natural balance.
     *
     * @return Collection<int, object{code: string, name: string, type: string, amount: float}>
     */
    public function periodActivityByType(string $type, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from = $from ?? now()->startOfYear();
        $to = $to ?? now()->endOfDay();

        return ChartOfAccount::query()
            ->where('is_active', true)
            ->where('type', $type)
            ->orderBy('code')
            ->get()
            ->map(function (ChartOfAccount $account) use ($from, $to) {
                $query = JournalEntryLine::query()
                    ->where('chart_of_account_id', $account->id)
                    ->whereHas('entry', function ($q) use ($from, $to) {
                        $q->where('status', 'posted')
                            ->whereDate('entry_date', '>=', $from->toDateString())
                            ->whereDate('entry_date', '<=', $to->toDateString());
                    });

                $debits = (float) (clone $query)->sum('debit');
                $credits = (float) (clone $query)->sum('credit');
                $amount = in_array($type, ['asset', 'expense'], true)
                    ? round($debits - $credits, 2)
                    : round($credits - $debits, 2);

                return (object) [
                    'code'   => $account->code,
                    'name'   => $account->name,
                    'type'   => $account->type,
                    'amount' => $amount,
                ];
            })
            ->filter(fn ($row) => abs($row->amount) >= 0.01)
            ->values();
    }

    /** Cash inflows / outflows from the configured cash GL account for a period. */
    public function cashMovements(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now()->endOfDay();
        $cashId = app(LedgerService::class)->cashAccountId();
        if (! $cashId) {
            return ['inflows' => 0.0, 'outflows' => 0.0, 'net' => 0.0, 'from_gl' => false];
        }

        $query = JournalEntryLine::query()
            ->where('chart_of_account_id', $cashId)
            ->whereHas('entry', function ($q) use ($from, $to) {
                $q->where('status', 'posted')
                    ->whereDate('entry_date', '>=', $from->toDateString())
                    ->whereDate('entry_date', '<=', $to->toDateString());
            });

        $inflows = round((float) (clone $query)->sum('debit'), 2);
        $outflows = round((float) (clone $query)->sum('credit'), 2);

        return [
            'inflows'  => $inflows,
            'outflows' => $outflows,
            'net'      => round($inflows - $outflows, 2),
            'from_gl'  => true,
        ];
    }

    public function hasPostedActivity(?Carbon $from = null, ?Carbon $to = null): bool
    {
        $q = JournalEntryLine::query()->whereHas('entry', function ($q) use ($from, $to) {
            $q->where('status', 'posted');
            if ($from) {
                $q->whereDate('entry_date', '>=', $from->toDateString());
            }
            if ($to) {
                $q->whereDate('entry_date', '<=', $to->toDateString());
            }
        });

        return $q->exists();
    }
}
