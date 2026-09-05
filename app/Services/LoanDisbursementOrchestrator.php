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
        app(\App\Services\Marketing\DemoGuard::class)->assertCanMoveMoney('disburse a loan');
        $loan = $loan->fresh(['application', 'customer', 'product', 'disbursements']);

        if ($loan->product && is_marketplace_loan_product($loan->product->code)) {
            return app(AssetHandoverService::class)->completeHandover($loan, $actor);
        }

        $released = $loan->disbursements->first(fn (Disbursement $row) => $row->status === Disbursement::STATUS_RELEASED);
        if ($released) {
            if ($loan->status === 'pending') {
                $loan->update([
                    'status' => 'active',
                    'disbursement_date' => $loan->disbursement_date ?? $released->released_at?->toDateString() ?? now()->toDateString(),
                ]);
            }

            return $loan->fresh(['customer', 'product', 'disbursements']);
        }

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

        $payoutBlock = app(GroupPayoutService::class)->blockingMessageForLoan($loan);
        if ($payoutBlock) {
            throw ValidationException::withMessages([
                'disburse' => $payoutBlock,
            ]);
        }

        $record = $existingDisbursement
            && (int) $existingDisbursement->loan_id === (int) $loan->id
            ? $existingDisbursement
            : $loan->disbursements
                ->first(fn (Disbursement $row) => in_array($row->status, [
                    Disbursement::STATUS_QUEUED,
                    Disbursement::STATUS_PROCESSING,
                    Disbursement::STATUS_FAILED,
                    'pending',
                ], true));

        if (! $record) {
            $record = Disbursement::create([
                'loan_id'     => $loan->id,
                'reference'   => 'DSB-'.strtoupper(Str::random(10)),
                'channel'     => $channel,
                'amount'      => (float) ($loan->principal_amount ?? 0),
                'status'      => Disbursement::STATUS_QUEUED,
                'approved_by' => $actor?->id,
                'notes'       => 'Queued for disbursement',
            ]);
        } else {
            $record->update([
                'channel' => $channel,
                'status'  => Disbursement::STATUS_QUEUED,
            ]);
        }

        $record->update(['status' => Disbursement::STATUS_PROCESSING]);

        try {
            $loan = DB::transaction(function () use ($loan, $actor, $channel, $record) {
                $this->capital->allocateForLoan($loan);

                $fees = $this->disbursement->applyFees($loan->fresh());
                $installments = $this->scheduler->generate($loan->fresh());

                $releasedAt = now();

                if ($loan->loan_application_id) {
                    $application = LoanApplication::find($loan->loan_application_id);
                    if ($application) {
                        $insuranceBlocks = $this->readiness->comprehensiveInsuranceBlockingMessages(
                            $application,
                            $releasedAt,
                        );
                        if ($insuranceBlocks !== []) {
                            throw ValidationException::withMessages([
                                'disburse' => implode(' ', $insuranceBlocks),
                            ]);
                        }
                    }
                }

                // Authoritative disbursement date is set only at Released.
                $loan->update([
                    'status'            => 'active',
                    'disbursement_date' => $releasedAt->toDateString(),
                ]);

                if ($loan->loan_application_id) {
                    app(LoanAgreementService::class)->generateRepaymentScheduleAnnex($loan->fresh());
                    // Separate final_loan_contract — do not mutate the signed pre-disbursement PDF.
                    app(LoanAgreementService::class)->generateFinalLoanContract($loan->fresh());
                }

                $netAmount = (float) ($loan->fresh()->net_disbursed_amount ?? $loan->principal_amount);
                $notes = 'Disbursed · '.count($fees).' fee(s) · '.$installments.' installment(s)';

                $record->update([
                    'channel'     => $channel,
                    'amount'      => $netAmount,
                    'status'      => Disbursement::STATUS_RELEASED,
                    'released_at' => $releasedAt,
                    'approved_by' => $actor?->id,
                    'notes'       => trim(($record->notes ? $record->notes.' · ' : '').$notes),
                ]);

                if ($loan->loan_application_id) {
                    $application = LoanApplication::find($loan->loan_application_id);
                    if ($application) {
                        $application->update([
                            'status'        => 'disbursed',
                            'current_stage' => 'disbursement',
                            'disbursed_at'  => $releasedAt,
                        ]);
                        app(AssetReservationService::class)->syncFromApplication($application->fresh());
                    }
                }

                app(GroupPayoutService::class)->markMemberDisbursed($loan->fresh());

                return $loan->fresh(['customer', 'product', 'disbursements', 'application.customer', 'repaymentSchedules']);
            });
        } catch (\Throwable $e) {
            $record->refresh();
            if ($record->status !== Disbursement::STATUS_RELEASED) {
                $record->update([
                    'status' => Disbursement::STATUS_FAILED,
                    'notes'  => trim(($record->notes ? $record->notes.' · ' : '').'Failed: '.$e->getMessage()),
                ]);
            }

            if ($e instanceof ValidationException) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'disburse' => 'Disbursement failed. The loan is not active. '.$e->getMessage(),
            ]);
        }

        app(LoanDisbursementNotificationService::class)->notifyDisbursement($loan);

        return $loan->fresh(['customer', 'product', 'disbursements']);
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

            $loan->disbursements()->where('status', Disbursement::STATUS_RELEASED)->update([
                'status' => Disbursement::STATUS_REVERSED,
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
