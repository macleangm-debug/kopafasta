<?php

namespace App\Services;

use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanTopUpRequest;
use App\Models\RepaymentSchedule;
use App\Models\RestructureRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoanDisbursementOrchestrator
{
    public function __construct(
        private readonly LoanDisbursementService $disbursement,
        private readonly RepaymentScheduleGenerator $scheduler,
        private readonly ApplicationDisbursementReadinessService $readiness,
        private readonly CapitalPartnerAllocationService $capital,
    ) {}

    public function disburse(Loan $loan, ?User $actor = null, string $channel = 'bank_transfer', ?Disbursement $existingDisbursement = null): Loan
    {
        return DB::transaction(function () use ($loan, $actor, $channel, $existingDisbursement) {
            $loan = $loan->fresh(['application', 'customer', 'product']);

            if (! in_array($loan->status, ['pending'], true)) {
                throw ValidationException::withMessages([
                    'loan' => 'Only pending loans can be disbursed.',
                ]);
            }

            if ($loan->loan_application_id) {
                $application = LoanApplication::find($loan->loan_application_id);
                if ($application) {
                    $blocking = $this->readiness->blockingMessages($application);
                    if ($blocking !== []) {
                        throw ValidationException::withMessages([
                            'disburse' => implode(' ', $blocking),
                        ]);
                    }
                }
            }

            $this->capital->allocateForLoan($loan);

            $loan->update([
                'status'            => 'active',
                'disbursement_date' => $loan->disbursement_date ?? now()->toDateString(),
            ]);

            $fees = $this->disbursement->applyFees($loan->fresh());
            $installments = $this->scheduler->generate($loan->fresh());

            if ($loan->loan_application_id) {
                app(LoanAgreementService::class)->generateRepaymentScheduleAnnex($loan->fresh());
                app(LoanAgreementService::class)->generateFinalLoanContract($loan->fresh());
            }

            $netAmount = (float) ($loan->fresh()->net_disbursed_amount ?? $loan->principal_amount);

            if (! $loan->disbursements()->where('status', 'released')->exists()) {
                $notes = 'Disbursed · '.count($fees).' fee(s) · '.$installments.' installment(s)';

                if ($existingDisbursement
                    && (int) $existingDisbursement->loan_id === (int) $loan->id
                    && $existingDisbursement->status !== 'released') {
                    $existingDisbursement->update([
                        'channel'     => $channel,
                        'amount'      => $netAmount,
                        'status'      => 'released',
                        'released_at' => now(),
                        'approved_by' => $actor?->id,
                        'notes'       => trim(($existingDisbursement->notes ? $existingDisbursement->notes.' · ' : '').$notes),
                    ]);
                } else {
                    Disbursement::create([
                        'loan_id'      => $loan->id,
                        'reference'    => 'DSB-'.strtoupper(Str::random(10)),
                        'channel'      => $channel,
                        'amount'       => $netAmount,
                        'status'       => 'released',
                        'released_at'  => now(),
                        'approved_by'  => $actor?->id,
                        'notes'        => $notes,
                    ]);
                }
            }

            if ($loan->loan_application_id) {
                $application = LoanApplication::find($loan->loan_application_id);
                if ($application) {
                    $application->update([
                        'status'        => 'disbursed',
                        'current_stage' => 'disbursement',
                        'disbursed_at'  => now(),
                    ]);
                    app(AssetReservationService::class)->syncFromApplication($application->fresh());
                }
            }

            app(LoanDisbursementNotificationService::class)->notifyDisbursement($loan->fresh(['application.customer', 'product', 'repaymentSchedules']));

            return $loan->fresh(['customer', 'product', 'disbursements']);
        });
    }

    /** @return list<string> */
    public function reverseBlockingMessages(Loan $loan): array
    {
        $messages = [];

        if (! in_array($loan->status, ['active'], true)) {
            $messages[] = 'Only active loans with no repayments can be reversed.';
        }

        if ($loan->repayments()->where('status', 'received')->exists()) {
            $messages[] = 'Cannot reverse — repayments have already been recorded.';
        }

        if ($loan->repaymentSchedules()->where('amount_paid', '>', 0)->exists()) {
            $messages[] = 'Cannot reverse — installments have partial payments.';
        }

        if (RestructureRequest::where('loan_id', $loan->id)->exists()
            || LoanTopUpRequest::where('loan_id', $loan->id)->exists()) {
            $messages[] = 'Cannot reverse — restructure or top-up requests exist.';
        }

        return $messages;
    }

    public function canReverseDisbursement(Loan $loan): bool
    {
        return $this->reverseBlockingMessages($loan) === [];
    }

    public function reverseDisbursement(Loan $loan, ?User $actor = null, ?string $reason = null): Loan
    {
        return DB::transaction(function () use ($loan, $actor, $reason) {
            $loan = $loan->fresh(['application', 'customer', 'product', 'capitalAllocations']);

            $blocking = $this->reverseBlockingMessages($loan);
            if ($blocking !== []) {
                throw ValidationException::withMessages([
                    'reverse' => implode(' ', $blocking),
                ]);
            }

            $this->capital->reverseAllocationForLoan(
                $loan,
                $reason ?: 'Disbursement reversed',
            );

            $this->disbursement->reverseFees($loan);

            RepaymentSchedule::where('loan_id', $loan->id)->delete();

            $loan->disbursements()->where('status', 'released')->update([
                'status' => 'cancelled',
                'notes'  => trim(($reason ?: 'Disbursement reversed').' · reversed by admin #'.($actor?->id ?? 'system')),
            ]);

            $loan->update([
                'status'              => 'pending',
                'disbursement_date'   => null,
                'next_due_date'       => null,
                'maturity_date'       => null,
                'outstanding_balance' => (float) $loan->principal_amount,
                'fees_total'          => 0,
                'net_disbursed_amount'=> null,
            ]);

            if ($loan->loan_application_id) {
                $application = LoanApplication::find($loan->loan_application_id);
                if ($application) {
                    $application->update([
                        'status'        => 'approved',
                        'current_stage' => 'disbursement',
                        'disbursed_at'  => null,
                    ]);
                    app(AssetReservationService::class)->syncFromApplication($application->fresh());
                }
            }

            return $loan->fresh(['customer', 'product', 'application']);
        });
    }
}
