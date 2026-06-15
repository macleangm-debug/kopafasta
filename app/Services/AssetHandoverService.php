<?php

namespace App\Services;

use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Asset lending activates the loan at handover — there is no cash disbursement to the borrower.
 */
class AssetHandoverService
{
    public function __construct(
        private readonly LoanDisbursementService $disbursement,
        private readonly RepaymentScheduleGenerator $scheduler,
        private readonly ApplicationDisbursementReadinessService $readiness,
        private readonly AssetLendingService $assetLending,
    ) {}

    public function canHandover(LoanApplication $application): bool
    {
        if (! $this->assetLending->isAssetLendingApplication($application)) {
            return false;
        }

        return $this->readiness->canMarkAssetHandover($application);
    }

    /** @return list<string> */
    public function blockingMessages(LoanApplication $application): array
    {
        if (! $this->assetLending->isAssetLendingApplication($application)) {
            return ['Not an asset lending application.'];
        }

        return $this->readiness->assetHandoverBlockingMessages($application);
    }

    public function completeHandover(Loan $loan, ?User $actor = null): Loan
    {
        return DB::transaction(function () use ($loan, $actor) {
            $loan = $loan->fresh(['application', 'customer', 'product']);

            if (! $this->assetLending->isAssetLendingProduct($loan->product)) {
                throw ValidationException::withMessages([
                    'loan' => 'Use standard disbursement for cash loans.',
                ]);
            }

            if (! in_array($loan->status, ['pending'], true)) {
                throw ValidationException::withMessages([
                    'loan' => 'Only pending loans can be handed over.',
                ]);
            }

            $application = $loan->application;
            if ($application) {
                $blocking = $this->blockingMessages($application);
                if ($blocking !== []) {
                    throw ValidationException::withMessages([
                        'handover' => implode(' ', $blocking),
                    ]);
                }
            }

            $loan->update([
                'status'            => 'active',
                'disbursement_date' => $loan->disbursement_date ?? now()->toDateString(),
                'net_disbursed_amount' => 0,
            ]);

            $fees = $this->disbursement->applyFees($loan->fresh());
            $installments = $this->scheduler->generate($loan->fresh());

            if ($loan->loan_application_id) {
                app(LoanAgreementService::class)->generateRepaymentScheduleAnnex($loan->fresh());
                app(LoanAgreementService::class)->generateFinalLoanContract($loan->fresh());
            }

            if (! $loan->disbursements()->where('status', 'released')->exists()) {
                Disbursement::create([
                    'loan_id'      => $loan->id,
                    'reference'    => 'HND-'.strtoupper(Str::random(10)),
                    'channel'      => 'asset_handover',
                    'amount'       => 0,
                    'status'       => 'released',
                    'released_at'  => now(),
                    'approved_by'  => $actor?->id,
                    'notes'        => 'Asset handover · no cash disbursement · '.count($fees).' fee(s) · '.$installments.' installment(s)',
                ]);
            }

            if ($application) {
                $application->update([
                    'status'        => 'disbursed',
                    'current_stage' => 'asset_handover',
                    'disbursed_at'  => now(),
                ]);

                app(AssetReservationService::class)->markHandoverComplete($application->fresh());
            }

            app(LoanDisbursementNotificationService::class)->notifyDisbursement($loan->fresh(['application.customer', 'product', 'repaymentSchedules']));

            return $loan->fresh(['customer', 'product', 'disbursements']);
        });
    }
}
