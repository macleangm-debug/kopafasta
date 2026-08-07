<?php

namespace App\Services;

use App\Models\ArrearCase;
use App\Models\AssetAuctionSettlement;
use App\Models\Loan;
use App\Models\LoanFee;
use App\Models\RecoveryAssignment;
use App\Models\Repayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuctionProceedsService
{
    public function __construct(
        private readonly LoanBalanceService $balances,
        private readonly RepaymentPostingService $repayments,
        private readonly LoanCollectionActionService $collectionActions,
    ) {}

    /**
     * Industry rule: apply outstanding first, then recovery costs; surplus returns to borrower.
     *
     * @return array{
     *     outstanding_applied: float,
     *     recovery_applied: float,
     *     borrower_refund: float,
     *     remaining_balance: float,
     *     loan_closed: bool
     * }
     */
    public function calculate(float $outstanding, float $auctionProceeds, float $recoveryCosts): array
    {
        $outstanding = round(max(0, $outstanding), 2);
        $auctionProceeds = round(max(0, $auctionProceeds), 2);
        $recoveryCosts = round(max(0, $recoveryCosts), 2);

        $outstandingApplied = round(min($outstanding, $auctionProceeds), 2);
        $remainingAfterLoan = round($auctionProceeds - $outstandingApplied, 2);
        $recoveryApplied = round(min($recoveryCosts, $remainingAfterLoan), 2);
        $borrowerRefund = round(max(0, $remainingAfterLoan - $recoveryApplied), 2);
        $remainingBalance = round(max(0, $outstanding - $outstandingApplied), 2);

        return [
            'outstanding_applied' => $outstandingApplied,
            'recovery_applied'    => $recoveryApplied,
            'borrower_refund'     => $borrowerRefund,
            'remaining_balance'   => $remainingBalance,
            'loan_closed'         => $remainingBalance <= 0.01,
        ];
    }

    public function settle(
        Loan $loan,
        float $auctionProceeds,
        User $actor,
        ?ArrearCase $arrearCase = null,
        ?RecoveryAssignment $assignment = null,
        ?string $notes = null,
    ): AssetAuctionSettlement {
        if ($auctionProceeds <= 0) {
            throw ValidationException::withMessages([
                'auction_proceeds' => 'Auction proceeds must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($loan, $auctionProceeds, $actor, $arrearCase, $assignment, $notes) {
            $breakdown = $this->balances->breakdown($loan);
            $outstanding = $breakdown['total_outstanding'];
            $recoveryCosts = $breakdown['recovery_costs'];

            $allocation = $this->calculate($outstanding, $auctionProceeds, $recoveryCosts);

            $repayment = null;
            if ($allocation['outstanding_applied'] > 0) {
                $alloc = $this->repayments->allocate($loan, $allocation['outstanding_applied']);

                $repayment = Repayment::create([
                    'loan_id'              => $loan->id,
                    'reference'            => 'AUC-'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
                    'channel'              => 'auction',
                    'amount'               => $allocation['outstanding_applied'],
                    'principal_component'  => $alloc['principal'],
                    'interest_component'   => $alloc['interest'],
                    'penalty_component'    => $alloc['penalty'],
                    'status'               => 'received',
                    'paid_at'              => now(),
                ]);

                $this->repayments->post($repayment);
            }

            if ($allocation['recovery_applied'] > 0) {
                $this->markRecoveryFeesPaid($loan, $allocation['recovery_applied']);
            }

            $this->balances->syncOutstandingBalance($loan->fresh());

            if ($allocation['loan_closed']) {
                $loan->update(['status' => 'closed']);
                $arrearCase?->update(['status' => 'closed']);
            } elseif ($arrearCase && $allocation['remaining_balance'] > 0) {
                $arrearCase->update([
                    'status' => 'open',
                    'notes'  => trim(($arrearCase->notes ? $arrearCase->notes."\n" : '')
                        .'Auction shortfall · remaining '.format_money($allocation['remaining_balance'])),
                ]);
            }

            $settlement = AssetAuctionSettlement::create([
                'loan_id'                => $loan->id,
                'arrear_case_id'         => $arrearCase?->id,
                'recovery_assignment_id' => $assignment?->id,
                'outstanding_before'     => $outstanding,
                'recovery_costs'         => $recoveryCosts,
                'auction_proceeds'       => $auctionProceeds,
                'outstanding_applied'    => $allocation['outstanding_applied'],
                'recovery_applied'       => $allocation['recovery_applied'],
                'borrower_refund'        => $allocation['borrower_refund'],
                'remaining_balance'      => $allocation['remaining_balance'],
                'loan_closed'            => $allocation['loan_closed'],
                'repayment_id'           => $repayment?->id,
                'recorded_by'            => $actor->id,
                'notes'                  => $notes,
                'settled_at'             => now(),
            ]);

            app(BorrowerRefundService::class)->createFromSettlement($settlement->fresh(['loan.customer']));

            if ($arrearCase) {
                $summary = sprintf(
                    'Auction settled · proceeds %s · loan applied %s · recovery %s · refund %s · balance %s',
                    format_money($auctionProceeds),
                    format_money($allocation['outstanding_applied']),
                    format_money($allocation['recovery_applied']),
                    format_money($allocation['borrower_refund']),
                    format_money($allocation['remaining_balance']),
                );

                $this->collectionActions->logForCase(
                    $arrearCase,
                    $actor,
                    'auction_settlement',
                    $summary,
                    $allocation['loan_closed'] ? 'closed' : 'partial',
                    null,
                    $assignment,
                );
            }

            return $settlement->fresh(['loan', 'repayment']);
        });
    }

    private function markRecoveryFeesPaid(Loan $loan, float $amount): void
    {
        $remaining = $amount;

        $fees = $loan->fees()
            ->whereNull('paid_at')
            ->where(function ($query): void {
                $query->whereIn('code', ['RECOVERY', 'LEGAL', 'COLLECTION'])
                    ->orWhere('code', 'like', 'RECOVERY\_%')
                    ->orWhere('type', 'recovery');
            })
            ->orderBy('id')
            ->get();

        foreach ($fees as $fee) {
            if ($remaining <= 0) {
                break;
            }

            $due = (float) $fee->computed_amount;
            if ($due <= $remaining + 0.01) {
                $fee->update(['paid_at' => now(), 'status' => 'paid']);
                $remaining = round($remaining - $due, 2);
            }
        }
    }
}
