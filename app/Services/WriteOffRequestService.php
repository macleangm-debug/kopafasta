<?php

namespace App\Services;

use App\Models\ArrearCase;
use App\Models\Loan;
use App\Models\Setting;
use App\Models\User;
use App\Models\WriteOffRequest;
use App\Models\WriteOffRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WriteOffRequestService
{
    public function __construct(
        private readonly LoanWriteOffService $writeOffs,
        private readonly ActiveLoanServicingService $servicing,
    ) {}

    public function recommend(
        Loan $loan,
        User $actor,
        string $reason,
        ?float $amount = null,
        ?ArrearCase $arrearCase = null,
        ?WriteOffRule $rule = null,
        bool $autoProposed = false,
    ): WriteOffRequest {
        $this->assertCanRecommend($actor);
        $this->assertLoanEligible($loan);

        if ($this->hasOpenRequest($loan)) {
            throw ValidationException::withMessages([
                'loan' => 'This loan already has a pending write-off request.',
            ]);
        }

        $amount = (float) ($amount ?? $loan->outstanding_balance);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Outstanding balance is zero — nothing to write off.',
            ]);
        }

        return WriteOffRequest::create([
            'loan_id'           => $loan->id,
            'arrear_case_id'    => $arrearCase?->id,
            'write_off_rule_id' => $rule?->id,
            'amount'            => $amount,
            'reason'            => $reason,
            'status'            => WriteOffRequest::STATUS_RECOMMENDED,
            'auto_proposed'     => $autoProposed,
            'recommended_by'    => $autoProposed ? null : $actor->id,
            'recommended_at'    => now(),
        ]);
    }

    public function managerApprove(WriteOffRequest $request, User $actor, ?string $notes = null): WriteOffRequest
    {
        $this->assertCanManagerApprove($actor);

        if ($request->status !== WriteOffRequest::STATUS_RECOMMENDED) {
            throw ValidationException::withMessages([
                'status' => 'Only recommended write-offs can receive manager approval.',
            ]);
        }

        $request->update([
            'status'              => WriteOffRequest::STATUS_MANAGER_APPROVED,
            'manager_approved_by' => $actor->id,
            'manager_approved_at' => now(),
            'manager_notes'       => $notes,
        ]);

        return $request->fresh(['loan.customer']);
    }

    public function financeApproveAndExecute(WriteOffRequest $request, User $actor, ?string $notes = null): WriteOffRequest
    {
        $this->assertCanFinanceApprove($actor);

        if ($request->status !== WriteOffRequest::STATUS_MANAGER_APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Manager approval is required before finance can execute the write-off.',
            ]);
        }

        return DB::transaction(function () use ($request, $actor, $notes) {
            $loan = $request->loan()->lockForUpdate()->firstOrFail();
            $reason = trim($request->reason.($notes ? "\nFinance: ".$notes : ''));

            $this->writeOffs->writeOff($loan, $reason, (float) $request->amount);

            $request->update([
                'status'              => WriteOffRequest::STATUS_COMPLETED,
                'finance_approved_by' => $actor->id,
                'finance_approved_at' => now(),
                'finance_notes'       => $notes,
                'completed_at'        => now(),
            ]);

            if ($request->arrear_case_id) {
                ArrearCase::where('id', $request->arrear_case_id)->update(['status' => 'resolved']);
            }

            return $request->fresh(['loan.customer']);
        });
    }

    public function reject(WriteOffRequest $request, User $actor, string $reason): WriteOffRequest
    {
        if (! $this->canReject($actor, $request)) {
            throw ValidationException::withMessages([
                'role' => 'You are not authorized to reject this write-off request.',
            ]);
        }

        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'This write-off request is no longer pending.',
            ]);
        }

        $request->update([
            'status'           => WriteOffRequest::STATUS_REJECTED,
            'rejected_by'      => $actor->id,
            'rejected_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        return $request->fresh();
    }

    /** @return list<WriteOffRequest> */
    public function proposeFromRules(): array
    {
        if (! (bool) Setting::get('finance.write_off_approval_required')) {
            return [];
        }

        $rules = WriteOffRule::query()
            ->where('is_active', true)
            ->where('auto_propose', true)
            ->get();

        $created = [];

        foreach ($rules as $rule) {
            $loans = Loan::query()
                ->whereIn('status', ['arrears', 'defaulted'])
                ->where('outstanding_balance', '>', 0)
                ->whereDoesntHave('writeOffRequests', fn ($q) => $q->whereIn('status', [
                    WriteOffRequest::STATUS_RECOMMENDED,
                    WriteOffRequest::STATUS_MANAGER_APPROVED,
                    WriteOffRequest::STATUS_COMPLETED,
                ]))
                ->with(['repaymentSchedules'])
                ->get()
                ->filter(fn (Loan $loan) => $this->loanMatchesRule($loan, $rule));

            foreach ($loans as $loan) {
                $created[] = $this->autoPropose($loan, $rule);
            }
        }

        return $created;
    }

    public function autoPropose(Loan $loan, WriteOffRule $rule): WriteOffRequest
    {
        $this->assertLoanEligible($loan);

        if ($this->hasOpenRequest($loan)) {
            throw ValidationException::withMessages([
                'loan' => 'This loan already has a pending write-off request.',
            ]);
        }

        return WriteOffRequest::create([
            'loan_id'           => $loan->id,
            'write_off_rule_id' => $rule->id,
            'amount'            => (float) $loan->outstanding_balance,
            'reason'            => 'Auto-proposed by rule: '.$rule->name,
            'status'            => WriteOffRequest::STATUS_RECOMMENDED,
            'auto_proposed'     => true,
            'recommended_at'    => now(),
        ]);
    }

    public function hasOpenRequest(Loan $loan): bool
    {
        return WriteOffRequest::query()
            ->where('loan_id', $loan->id)
            ->whereIn('status', [WriteOffRequest::STATUS_RECOMMENDED, WriteOffRequest::STATUS_MANAGER_APPROVED])
            ->exists();
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            WriteOffRequest::STATUS_RECOMMENDED       => 'Awaiting manager',
            WriteOffRequest::STATUS_MANAGER_APPROVED  => 'Awaiting finance',
            WriteOffRequest::STATUS_COMPLETED         => 'Written off',
            WriteOffRequest::STATUS_REJECTED          => 'Rejected',
            default                                   => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function loanMatchesRule(Loan $loan, WriteOffRule $rule): bool
    {
        $metrics = $this->servicing->forLoan($loan);
        $daysPastDue = (int) ($metrics['days_past_due'] ?? 0);

        if ($daysPastDue < (int) $rule->days_past_due) {
            return false;
        }

        $outstanding = (float) $loan->outstanding_balance;

        if ($rule->min_outstanding !== null && $outstanding < (float) $rule->min_outstanding) {
            return false;
        }

        if ($rule->max_outstanding !== null && $outstanding > (float) $rule->max_outstanding) {
            return false;
        }

        return true;
    }

    private function assertLoanEligible(Loan $loan): void
    {
        if (! in_array($loan->status, ['arrears', 'defaulted'], true)) {
            throw ValidationException::withMessages([
                'loan' => 'Only loans in arrears or default can be recommended for write-off.',
            ]);
        }

        if ((float) $loan->outstanding_balance <= 0) {
            throw ValidationException::withMessages([
                'loan' => 'Loan has no outstanding balance.',
            ]);
        }
    }

    public function assertCanRecommend(User $user): void
    {
        if (! $this->canRecommend($user)) {
            throw ValidationException::withMessages([
                'role' => 'You are not authorized to recommend write-offs.',
            ]);
        }
    }

    public function canRecommend(User $user): bool
    {
        return in_array($user->role, ['collector', 'manager', 'admin', 'super_admin'], true);
    }

    public function canSeeWriteOffActions(User $user): bool
    {
        return $this->canRecommend($user)
            || $this->canManagerApprove($user)
            || $this->canFinanceApprove($user);
    }

    public function loanEligibleForRecommendation(Loan $loan): bool
    {
        return in_array($loan->status, ['arrears', 'defaulted'], true)
            && (float) $loan->outstanding_balance > 0;
    }

    public function canAccessWriteOffForm(User $user, Loan $loan): bool
    {
        if (! $this->loanEligibleForRecommendation($loan)) {
            return false;
        }

        $approvalRequired = (bool) Setting::get('finance.write_off_approval_required');

        return $approvalRequired
            ? $this->canRecommend($user)
            : $this->canFinanceApprove($user);
    }

    public function canManagerApprove(User $user): bool
    {
        return in_array($user->role, ['manager', 'admin', 'super_admin'], true);
    }

    public function canFinanceApprove(User $user): bool
    {
        return in_array($user->role, ['admin', 'super_admin'], true)
            || $user->hasPermission('finance.operations');
    }

    public function canReject(User $user, WriteOffRequest $request): bool
    {
        if ($this->canManagerApprove($user) || $this->canFinanceApprove($user)) {
            return true;
        }

        return $this->canRecommend($user) && (int) $request->recommended_by === (int) $user->id;
    }

    private function assertCanManagerApprove(User $user): void
    {
        if (! $this->canManagerApprove($user)) {
            throw ValidationException::withMessages([
                'role' => 'Manager approval required.',
            ]);
        }
    }

    private function assertCanFinanceApprove(User $user): void
    {
        if (! $this->canFinanceApprove($user)) {
            throw ValidationException::withMessages([
                'role' => 'Finance approval required.',
            ]);
        }

        if (! (bool) Setting::get('finance.write_off_approval_required')) {
            throw ValidationException::withMessages([
                'setting' => 'Write-off approval workflow is disabled in finance settings.',
            ]);
        }
    }
}
