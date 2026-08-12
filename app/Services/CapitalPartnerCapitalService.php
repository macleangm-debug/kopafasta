<?php

namespace App\Services;

use App\Models\CapitalWithdrawalRequest;
use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\LenderTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CapitalPartnerCapitalService
{
    public function __construct(
        protected AuditService $audit,
        protected CapitalPartnerMetricsService $metrics,
    ) {}

    /** Increase partner capital (deposit / top-up). */
    public function increaseCapital(Lender $lender, float $amount, ?string $notes, ?User $actor): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        DB::transaction(function () use ($lender, $amount, $notes, $actor): void {
            $pool = $this->primaryPool($lender, createIfMissing: true);
            $before = $this->audit->snapshot($lender, ['credit_limit', 'available_balance']);

            $pool->amount_committed = (float) $pool->amount_committed + $amount;
            $pool->save();

            $lender->credit_limit = (float) ($lender->credit_limit ?? 0) + $amount;
            $lender->available_balance = (float) ($lender->available_balance ?? 0) + $amount;
            $lender->save();

            $this->recordTransaction(
                $lender,
                $pool,
                'deposit',
                'credit',
                $amount,
                $notes ?? 'Capital deposit',
                null,
                $actor,
            );

            $this->postCapitalCashJournal($lender, $amount, 'deposit', 'Capital deposit · '.$lender->name);

            $this->audit->log($actor, 'capital_partner.capital_deposit', $lender, $before, $this->audit->snapshot($lender, ['credit_limit', 'available_balance']));
        });
    }

    /** Decrease available capital (admin adjustment — cannot reduce below deployed). */
    public function decreaseCapital(Lender $lender, float $amount, ?string $notes, ?User $actor): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        $available = $this->metrics->forLender($lender)['capital_available'];
        if ($amount > $available) {
            throw ValidationException::withMessages([
                'amount' => 'Cannot reduce more than available capital ('.format_money($available).'). Allocated funds on active loans cannot be withdrawn.',
            ]);
        }

        DB::transaction(function () use ($lender, $amount, $notes, $actor): void {
            $pool = $this->primaryPool($lender);
            $before = $this->audit->snapshot($lender, ['credit_limit', 'available_balance']);

            $pool->amount_committed = max((float) $pool->amount_deployed, (float) $pool->amount_committed - $amount);
            $pool->save();

            $lender->credit_limit = max(0, (float) ($lender->credit_limit ?? 0) - $amount);
            $lender->available_balance = max(0, (float) ($lender->available_balance ?? 0) - $amount);
            $lender->save();

            $this->recordTransaction(
                $lender,
                $pool,
                'admin_adjustment',
                'debit',
                $amount,
                $notes ?? 'Administrative capital reduction',
                null,
                $actor,
            );

            $this->audit->log($actor, 'capital_partner.capital_adjustment', $lender, $before, $this->audit->snapshot($lender, ['credit_limit', 'available_balance']));
        });
    }

    public function requestWithdrawal(Lender $lender, float $amount, ?string $notes, ?User $actor): CapitalWithdrawalRequest
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        $available = $this->metrics->forLender($lender)['capital_available'];
        if ($amount > $available) {
            throw ValidationException::withMessages([
                'amount' => 'Only unused capital can be withdrawn. Available: '.format_money($available),
            ]);
        }

        $pool = $this->primaryPool($lender);

        return CapitalWithdrawalRequest::create([
            'lender_id'       => $lender->id,
            'funding_pool_id' => $pool?->id,
            'amount'          => $amount,
            'status'          => 'pending',
            'notes'           => $notes,
            'requested_by'    => $actor?->id,
        ]);
    }

    public function approveWithdrawal(CapitalWithdrawalRequest $request, ?string $adminNotes, ?User $actor): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This withdrawal request has already been processed.']);
        }

        $lender = $request->lender;
        $available = $this->metrics->forLender($lender)['capital_available'];
        if ((float) $request->amount > $available) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient available capital at approval time ('.format_money($available).').',
            ]);
        }

        DB::transaction(function () use ($request, $adminNotes, $actor, $lender): void {
            $pool = $request->pool ?? $this->primaryPool($lender);
            $amount = (float) $request->amount;
            $before = $this->audit->snapshot($lender, ['available_balance']);

            $pool->amount_committed = max((float) $pool->amount_deployed, (float) $pool->amount_committed - $amount);
            $pool->save();

            $lender->available_balance = max(0, (float) ($lender->available_balance ?? 0) - $amount);
            $lender->credit_limit = max((float) $pool->amount_deployed, (float) ($lender->credit_limit ?? 0) - $amount);
            $lender->save();

            $txn = $this->recordTransaction(
                $lender,
                $pool,
                'withdrawal',
                'debit',
                $amount,
                $request->notes ?? 'Approved capital withdrawal',
                null,
                $actor,
            );

            $this->postCapitalCashJournal($lender, $amount, 'withdrawal', 'Capital withdrawal · '.$lender->name);

            $request->update([
                'status'       => 'approved',
                'admin_notes'  => $adminNotes,
                'reviewed_by'  => $actor?->id,
                'reviewed_at'  => now(),
            ]);

            $this->audit->log($actor, 'capital_partner.withdrawal_approved', $request, [], [
                'amount'     => $amount,
                'lender_id'  => $lender->id,
                'reference'  => $txn->reference,
            ]);
            $this->audit->log($actor, 'capital_partner.capital_withdrawal', $lender, $before, $this->audit->snapshot($lender, ['available_balance']));
        });
    }

    public function rejectWithdrawal(CapitalWithdrawalRequest $request, ?string $adminNotes, ?User $actor): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This withdrawal request has already been processed.']);
        }

        $request->update([
            'status'      => 'rejected',
            'admin_notes' => $adminNotes,
            'reviewed_by' => $actor?->id,
            'reviewed_at' => now(),
        ]);

        $this->audit->log($actor, 'capital_partner.withdrawal_rejected', $request, [], [
            'amount'    => (float) $request->amount,
            'lender_id' => $request->lender_id,
        ]);
    }

    protected function primaryPool(Lender $lender, bool $createIfMissing = false): FundingPool
    {
        $lender->loadMissing('pools');
        $pool = $lender->pools->firstWhere('status', 'open') ?? $lender->pools->first();

        if ($pool) {
            return $pool;
        }

        if (! $createIfMissing) {
            throw ValidationException::withMessages([
                'pool' => 'No funding pool exists for this partner. Add a pool or use capital deposit to create one.',
            ]);
        }

        return FundingPool::create([
            'lender_id'        => $lender->id,
            'name'             => $lender->name.' Primary Pool',
            'currency'         => 'TZS',
            'amount_committed' => 0,
            'amount_deployed'  => 0,
            'expected_yield'   => 0,
            'status'           => 'open',
            'is_public'        => false,
            'start_date'       => now()->toDateString(),
        ]);
    }

    protected function recordTransaction(
        Lender $lender,
        FundingPool $pool,
        string $type,
        string $direction,
        float $amount,
        string $notes,
        ?int $loanId,
        ?User $actor,
    ): LenderTransaction {
        return LenderTransaction::create([
            'lender_id'        => $lender->id,
            'funding_pool_id'  => $pool->id,
            'loan_id'          => $loanId,
            'reference'        => 'TXN-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'type'             => $type,
            'direction'        => $direction,
            'amount'           => $amount,
            'status'           => 'completed',
            'channel'          => 'system',
            'notes'            => $notes,
            'processed_at'     => now(),
            'created_by'       => $actor?->id,
        ]);
    }

    /**
     * Deposit: Dr cash, Cr capital partner pool.
     * Withdrawal: Dr capital partner pool, Cr cash.
     */
    protected function postCapitalCashJournal(Lender $lender, float $amount, string $kind, string $description): void
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return;
        }

        $ledger = app(LedgerService::class);
        $cashId = $ledger->cashAccountId();
        $poolId = $ledger->capitalPartnerPoolAccountId();
        if (! $cashId || ! $poolId) {
            return;
        }

        if ($kind === 'deposit') {
            $lines = [
                ['account_id' => $cashId, 'debit' => $amount, 'credit' => 0, 'description' => 'Cash received'],
                ['account_id' => $poolId, 'debit' => 0, 'credit' => $amount, 'description' => 'Due to capital partner'],
            ];
        } else {
            $lines = [
                ['account_id' => $poolId, 'debit' => $amount, 'credit' => 0, 'description' => 'Capital partner payout'],
                ['account_id' => $cashId, 'debit' => 0, 'credit' => $amount, 'description' => 'Cash paid'],
            ];
        }

        $ledger->post($lines, $description, $lender, now()->toDateString());
    }
}
