<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\RepaymentSchedule;

class LoanDisbursementNotificationService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly GuarantorNotificationService $guarantors,
    ) {}

    public function notifyDisbursement(Loan $loan): void
    {
        $loan->loadMissing(['application.customer', 'product', 'repaymentSchedules']);
        $application = $loan->application ?? LoanApplication::find($loan->loan_application_id);

        if (! $application?->customer) {
            return;
        }

        $customer = $application->customer;
        $firstRepayment = $loan->repaymentSchedules
            ->sortBy('installment_no')
            ->first(fn (RepaymentSchedule $row) => $row->due_date !== null);

        $finalContract = LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'final_loan_contract')
            ->latest('id')
            ->first();

        $contractUrl = $finalContract
            ? route('site.borrower.loans.final-contract', $loan)
            : route('site.borrower.loans.show', $loan);

        $title = __('borrower.loan_servicing.disbursed_notify_title');
        $message = __('borrower.loan_servicing.disbursed_notify_message', [
            'reference'        => $loan->loan_number,
            'amount'           => format_money((float) $loan->principal_amount),
            'disbursement_date'=> optional($loan->disbursement_date)->format('d-M-Y') ?? now()->format('d-M-Y'),
            'first_repayment'  => optional($firstRepayment?->due_date)->format('d-M-Y') ?? '—',
        ]);

        $this->notifications->notifyInApp(
            $customer,
            $message,
            'loan',
            'loan_disbursed',
            $title,
            $contractUrl,
            __('borrower.loan_servicing.view_final_contract'),
        );

        if ($customer->phone) {
            $this->notifications->notifyCustomer(
                $customer,
                'loan_disbursed',
                [
                    'name'              => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Customer',
                    'reference'         => $loan->loan_number,
                    'loan_number'       => $loan->loan_number,
                    'amount'            => format_money((float) $loan->principal_amount),
                    'disbursement_date' => optional($loan->disbursement_date)->format('d M Y') ?? now()->format('d M Y'),
                    'first_repayment'   => optional($firstRepayment?->due_date)->format('d M Y') ?? '—',
                    'due_date'          => optional($firstRepayment?->due_date)->format('d M Y') ?? '—',
                    '_fallback_body'    => $message,
                    '_fallback_subject' => $title,
                ],
            );
        }

        $this->guarantors->notifyLoanDisbursed($loan);
    }
}
