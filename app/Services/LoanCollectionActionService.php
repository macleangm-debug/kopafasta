<?php

namespace App\Services;

use App\Models\ArrearCase;
use App\Models\CollectionAction;
use App\Models\Loan;
use App\Models\User;

class LoanCollectionActionService
{
    public function __construct(
        private readonly ActiveLoanServicingService $servicing,
    ) {}

    public function logForLoan(
        Loan $loan,
        User $actor,
        string $actionType,
        ?string $notes = null,
        ?string $result = null,
    ): CollectionAction {
        $metrics = $this->servicing->forLoan($loan);

        $arrearCase = ArrearCase::query()
            ->where('loan_id', $loan->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if (! $arrearCase) {
            $arrearCase = ArrearCase::create([
                'loan_id'           => $loan->id,
                'days_past_due'     => max(0, -1 * (int) ($metrics['days_remaining'] ?? 0)),
                'amount_in_arrears' => (float) ($metrics['amount_in_arrears'] ?? 0),
                'penalty_amount'    => 0,
                'status'            => 'open',
                'assigned_to'       => $actor->id,
            ]);
        }

        return $this->logForCase($arrearCase, $actor, $actionType, $notes, $result, $metrics);
    }

    /** @param  array<string, mixed>|null  $metrics */
    public function logForCase(
        ArrearCase $arrearCase,
        User $actor,
        string $actionType,
        ?string $notes = null,
        ?string $result = null,
        ?array $metrics = null,
    ): CollectionAction {
        $arrearCase->loadMissing('loan');
        $metrics ??= $arrearCase->loan
            ? $this->servicing->forLoan($arrearCase->loan)
            : [];

        $action = CollectionAction::create([
            'arrear_case_id' => $arrearCase->id,
            'performed_by'   => $actor->id,
            'action_type'    => $actionType,
            'notes'          => $notes,
            'result'         => $result,
            'performed_at'   => now(),
        ]);

        $arrearCase->update([
            'last_follow_up_at' => now(),
            'amount_in_arrears' => (float) ($metrics['amount_in_arrears'] ?? $arrearCase->amount_in_arrears),
            'days_past_due'     => max(
                (int) $arrearCase->days_past_due,
                max(0, -1 * (int) ($metrics['days_remaining'] ?? 0)),
            ),
        ]);

        return $action;
    }
}
