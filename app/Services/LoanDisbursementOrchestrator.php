<?php

namespace App\Services;

use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanApplication;
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
    ) {}

    public function disburse(Loan $loan, ?User $actor = null, string $channel = 'bank_transfer'): Loan
    {
        return DB::transaction(function () use ($loan, $actor, $channel) {
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

            $loan->update([
                'status'            => 'active',
                'disbursement_date' => $loan->disbursement_date ?? now()->toDateString(),
            ]);

            $fees = $this->disbursement->applyFees($loan->fresh());
            $installments = $this->scheduler->generate($loan->fresh());

            $netAmount = (float) ($loan->fresh()->net_disbursed_amount ?? $loan->principal_amount);

            if (! $loan->disbursements()->where('status', 'released')->exists()) {
                Disbursement::create([
                    'loan_id'      => $loan->id,
                    'reference'    => 'DSB-'.strtoupper(Str::random(10)),
                    'channel'      => $channel,
                    'amount'       => $netAmount,
                    'status'       => 'released',
                    'released_at'  => now(),
                    'approved_by'  => $actor?->id,
                    'notes'        => 'Disbursed via admin · '.count($fees).' fee(s) · '.$installments.' installment(s)',
                ]);
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

            app(GuarantorNotificationService::class)->notifyLoanDisbursed($loan->fresh(['application.customer', 'product']));

            return $loan->fresh(['customer', 'product', 'disbursements']);
        });
    }
}
