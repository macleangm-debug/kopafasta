<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanTopUpRequest;
use App\Models\NotificationLog;
use App\Models\RestructureRequest;
use App\Models\User;

class LoanRequestReviewService
{
    public function approveRestructure(RestructureRequest $request, User $actor, ?string $notes = null): RestructureRequest
    {
        if ($request->status !== 'pending') {
            throw new \InvalidArgumentException('Only pending restructure requests can be approved.');
        }

        $loan = $request->loan ?? Loan::findOrFail($request->loan_id);

        $request->update([
            'status'         => 'approved',
            'approved_by'    => $actor->id,
            'approved_at'    => now(),
            'decision_notes' => $notes,
        ]);

        $loan->update([
            'tenure_months'  => $request->new_tenure_months ?? $loan->tenure_months,
            'interest_rate'  => $request->new_interest_rate ?? $loan->interest_rate,
            'status'         => $request->restructure_type === 'payment_holiday' ? 'restructuring' : $loan->status,
        ]);

        $loan = $loan->fresh();
        $installments = app(RepaymentScheduleGenerator::class)->regenerateRemaining($loan);

        $this->notifyBorrower(
            $request->customer_id ?? $loan->customer_id,
            'restructure_approved',
            'Restructure request approved',
            'Your loan restructure request has been approved. '.$installments.' new instalment(s) scheduled.',
        );

        return $request->fresh(['loan']);
    }

    public function rejectRestructure(RestructureRequest $request, User $actor, ?string $notes = null): RestructureRequest
    {
        if ($request->status !== 'pending') {
            throw new \InvalidArgumentException('Only pending restructure requests can be rejected.');
        }

        $request->update([
            'status'         => 'rejected',
            'approved_by'    => $actor->id,
            'approved_at'    => now(),
            'decision_notes' => $notes,
        ]);

        $this->notifyBorrower(
            $request->customer_id ?? $request->loan?->customer_id,
            'restructure_rejected',
            'Restructure request declined',
            $notes ?: 'Your restructure request was not approved. Contact support for more information.',
        );

        return $request->fresh(['loan']);
    }

    public function approveTopUp(LoanTopUpRequest $request, User $actor, ?string $notes = null): LoanTopUpRequest
    {
        if ($request->status !== 'pending') {
            throw new \InvalidArgumentException('Only pending top-up requests can be approved.');
        }

        $loan = $request->loan ?? Loan::findOrFail($request->loan_id);
        $amount = (float) $request->requested_amount;

        $request->update([
            'status'         => 'approved',
            'reviewed_by'    => $actor->id,
            'reviewed_at'    => now(),
            'decision_notes' => $notes,
        ]);

        $loan->update([
            'approved_amount'     => (float) $loan->approved_amount + $amount,
            'outstanding_balance' => (float) $loan->outstanding_balance + $amount,
        ]);

        $loan = $loan->fresh();
        $installments = app(RepaymentScheduleGenerator::class)->regenerateRemaining($loan);

        $this->notifyBorrower(
            $request->customer_id,
            'top_up_approved',
            'Top-up request approved',
            'Your top-up of '.format_money($amount).' has been approved. '.$installments.' instalment(s) updated.',
        );

        return $request->fresh(['loan']);
    }

    public function rejectTopUp(LoanTopUpRequest $request, User $actor, ?string $notes = null): LoanTopUpRequest
    {
        if ($request->status !== 'pending') {
            throw new \InvalidArgumentException('Only pending top-up requests can be rejected.');
        }

        $request->update([
            'status'         => 'rejected',
            'reviewed_by'    => $actor->id,
            'reviewed_at'    => now(),
            'decision_notes' => $notes,
        ]);

        $this->notifyBorrower(
            $request->customer_id,
            'top_up_rejected',
            'Top-up request declined',
            $notes ?: 'Your top-up request was not approved. Contact support for more information.',
        );

        return $request->fresh(['loan']);
    }

    private function notifyBorrower(?int $customerId, string $template, string $title, string $message): void
    {
        if (! $customerId) {
            return;
        }

        NotificationLog::create([
            'customer_id' => $customerId,
            'channel'     => 'in_app',
            'template'    => $template,
            'recipient'   => '/borrower/loans?tab=active',
            'message'     => $title."\n".$message,
            'status'      => 'sent',
            'sent_at'     => now(),
            'category'    => 'loan',
        ]);
    }
}
