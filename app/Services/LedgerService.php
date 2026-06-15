<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * Post a balanced journal entry.
     *
     * $lines: [ ['account_id'=>X, 'debit'=>0, 'credit'=>0, 'description'=>'...'], ... ]
     */
    public function post(array $lines, string $description, ?Model $source = null, ?string $entryDate = null, ?string $memo = null): ?JournalEntry
    {
        $lines = array_values(array_filter($lines, fn ($l) => ((float) ($l['debit'] ?? 0)) > 0 || ((float) ($l['credit'] ?? 0)) > 0));
        if (empty($lines)) return null;

        $totalDr = array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $lines));
        $totalCr = array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $lines));
        if (round($totalDr, 2) !== round($totalCr, 2)) {
            throw new \RuntimeException("Journal not balanced: Dr {$totalDr} vs Cr {$totalCr}");
        }

        return DB::transaction(function () use ($lines, $description, $source, $entryDate, $memo, $totalDr, $totalCr) {
            $entry = JournalEntry::create([
                'entry_number'      => $this->nextNumber(),
                'entry_date'        => $entryDate ?? now()->toDateString(),
                'description'       => $description,
                'source_type'       => $source ? $source::class : null,
                'source_id'         => $source?->getKey(),
                'posted_by'         => auth()->id(),
                'posted_at'         => now(),
                'status'            => 'posted',
                'total_debit'       => $totalDr,
                'total_credit'      => $totalCr,
                'memo'              => $memo,
            ]);

            foreach ($lines as $i => $l) {
                JournalEntryLine::create([
                    'journal_entry_id'    => $entry->id,
                    'chart_of_account_id' => (int) $l['account_id'],
                    'debit'               => (float) ($l['debit'] ?? 0),
                    'credit'              => (float) ($l['credit'] ?? 0),
                    'description'         => $l['description'] ?? null,
                    'line_no'             => $i + 1,
                ]);
            }

            return $entry->fresh('lines');
        });
    }

    public function nextNumber(): string
    {
        $prefix = 'JE-'.now()->format('Ym').'-';
        $last = JournalEntry::where('entry_number', 'like', $prefix.'%')->orderByDesc('id')->value('entry_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve the configured offset (cash/bank) GL account for disbursement,
     * falling back to the first asset account if not configured.
     */
    public function cashAccountId(): ?int
    {
        $id = (int) (Setting::get('finance.cash_gl_account_id') ?? 0);
        if ($id > 0 && ChartOfAccount::whereKey($id)->exists()) return $id;
        return ChartOfAccount::where('type', 'asset')->orderBy('id')->value('id');
    }

    public function loanReceivableAccountId(): ?int
    {
        $id = (int) (Setting::get('finance.loan_receivable_gl_account_id') ?? 0);
        if ($id > 0 && ChartOfAccount::whereKey($id)->exists()) {
            return $id;
        }

        return ChartOfAccount::where('type', 'asset')->where('name', 'like', '%loan%')->orderBy('id')->value('id');
    }

    public function capitalPartnerPoolAccountId(): ?int
    {
        $id = (int) (Setting::get('finance.capital_partner_pool_gl_account_id') ?? 0);
        if ($id > 0 && ChartOfAccount::whereKey($id)->exists()) {
            return $id;
        }

        return ChartOfAccount::query()
            ->where(function ($q) {
                $q->where('name', 'like', '%capital%partner%')
                    ->orWhere('name', 'like', '%partner%pool%')
                    ->orWhere('name', 'like', '%due to%partner%');
            })
            ->orderBy('id')
            ->value('id');
    }

    /** Liability account for fees withheld at disbursement (recognized as income later). */
    public function deferredFeeLiabilityAccountId(): ?int
    {
        $id = (int) (Setting::get('finance.deferred_fee_liability_gl_account_id') ?? 0);
        if ($id > 0 && ChartOfAccount::whereKey($id)->exists()) {
            return $id;
        }

        return ChartOfAccount::query()
            ->where('type', 'liability')
            ->where(function ($q) {
                $q->where('name', 'like', '%deferred%fee%')
                    ->orWhere('name', 'like', '%unearned%fee%');
            })
            ->orderBy('code')
            ->value('id');
    }

    /** Liability for auction surpluses owed back to borrowers. */
    public function borrowerRefundsPayableAccountId(): ?int
    {
        $id = (int) (Setting::get('finance.borrower_refunds_payable_gl_account_id') ?? 0);
        if ($id > 0 && ChartOfAccount::whereKey($id)->exists()) {
            return $id;
        }

        return ChartOfAccount::query()
            ->where('type', 'liability')
            ->where(function ($q) {
                $q->where('name', 'like', '%borrower%refund%')
                    ->orWhere('name', 'like', '%customer%refund%')
                    ->orWhere('name', 'like', '%refund%payable%');
            })
            ->orderBy('code')
            ->value('id');
    }

    public function recoveryRevenueAccountId(): ?int
    {
        $id = (int) (Setting::get('finance.recovery_revenue_gl_account_id') ?? 0);
        if ($id > 0 && ChartOfAccount::whereKey($id)->exists()) {
            return $id;
        }

        return ChartOfAccount::query()
            ->where('type', 'income')
            ->where('name', 'like', '%recovery%revenue%')
            ->orderBy('code')
            ->value('id');
    }

    public function recoveryPartnerPayableAccountId(): ?int
    {
        $id = (int) (Setting::get('finance.recovery_partner_payable_gl_account_id') ?? 0);
        if ($id > 0 && ChartOfAccount::whereKey($id)->exists()) {
            return $id;
        }

        return ChartOfAccount::query()
            ->where('type', 'liability')
            ->where(function ($q) {
                $q->where('name', 'like', '%recovery%partner%')
                    ->orWhere('name', 'like', '%partner%payable%');
            })
            ->orderBy('code')
            ->value('id');
    }

    public function valuationRevenueAccountId(): ?int
    {
        $id = (int) (Setting::get('finance.valuation_revenue_gl_account_id') ?? 0);
        if ($id > 0 && ChartOfAccount::whereKey($id)->exists()) {
            return $id;
        }

        return ChartOfAccount::query()->where('type', 'income')->where('name', 'like', '%valuation%')->orderBy('code')->value('id');
    }

    public function gpsRevenueAccountId(): ?int
    {
        $id = (int) (Setting::get('finance.gps_revenue_gl_account_id') ?? 0);
        if ($id > 0 && ChartOfAccount::whereKey($id)->exists()) {
            return $id;
        }

        return ChartOfAccount::query()->where('type', 'income')->where('name', 'like', '%gps%')->orderBy('code')->value('id');
    }

    public function assetLendingRevenueAccountId(): ?int
    {
        $id = (int) (Setting::get('finance.asset_lending_revenue_gl_account_id') ?? 0);
        if ($id > 0 && ChartOfAccount::whereKey($id)->exists()) {
            return $id;
        }

        return ChartOfAccount::query()
            ->where('type', 'income')
            ->where(function ($q) {
                $q->where('name', 'like', '%asset%lending%')
                    ->orWhere('name', 'like', '%asset%finance%');
            })
            ->orderBy('code')
            ->value('id');
    }
}
