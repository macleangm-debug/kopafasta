<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Loan;
use App\Models\LoanApplication;

class GuarantorNotificationService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly GuarantorAccessService $access,
        private readonly GuaranteedLoanService $guaranteedLoans,
    ) {}

    public function notifyLoanApproved(LoanApplication $application): void
    {
        $application->loadMissing(['customer', 'product']);
        $this->notifyGuarantors($application, 'guarantor_loan_approved', [
            'title'   => __('borrower.guaranteed.notify.approved_title'),
            'message' => __('borrower.guaranteed.notify.approved_message', [
                'borrower'  => $application->customer?->legalDisplayName() ?? 'Borrower',
                'reference' => $application->application_number ?? '—',
                'product'   => $application->product?->name ?? __('borrower.guarantor.loan'),
            ]),
        ]);
    }

    public function notifyLoanDisbursed(Loan $loan): void
    {
        $application = $loan->application ?? LoanApplication::find($loan->loan_application_id);
        if (! $application) {
            return;
        }

        $application->loadMissing(['customer', 'product']);
        $this->notifyGuarantors($application, 'guarantor_loan_disbursed', [
            'title'   => __('borrower.guaranteed.notify.disbursed_title'),
            'message' => __('borrower.guaranteed.notify.disbursed_message', [
                'borrower'  => $application->customer?->legalDisplayName() ?? 'Borrower',
                'reference' => $loan->loan_number,
                'amount'    => format_money((float) $loan->principal_amount),
            ]),
        ]);
    }

    public function notifyLoanArrears(Loan $loan): void
    {
        $application = $loan->application ?? LoanApplication::find($loan->loan_application_id);
        if (! $application) {
            return;
        }

        $application->loadMissing(['customer']);
        $this->notifyGuarantors($application, 'guarantor_loan_arrears', [
            'title'   => __('borrower.guaranteed.notify.arrears_title'),
            'message' => __('borrower.guaranteed.notify.arrears_message', [
                'borrower'  => $application->customer?->legalDisplayName() ?? 'Borrower',
                'reference' => $loan->loan_number,
                'balance'   => format_money((float) $loan->outstanding_balance),
            ]),
        ]);
    }

    public function notifyLoanClosed(Loan $loan): void
    {
        $application = $loan->application ?? LoanApplication::find($loan->loan_application_id);
        if (! $application) {
            return;
        }

        $application->loadMissing(['customer']);
        $this->notifyGuarantors($application, 'guarantor_loan_closed', [
            'title'   => __('borrower.guaranteed.notify.closed_title'),
            'message' => __('borrower.guaranteed.notify.closed_message', [
                'borrower'  => $application->customer?->legalDisplayName() ?? 'Borrower',
                'reference' => $loan->loan_number,
            ]),
        ]);
    }

    public function notifyRestructureRequested(Loan $loan, string $type): void
    {
        $application = $loan->application ?? LoanApplication::find($loan->loan_application_id);
        if (! $application) {
            return;
        }

        $application->loadMissing(['customer']);
        $this->notifyGuarantors($application, 'guarantor_restructure_requested', [
            'title'   => __('borrower.guaranteed.notify.restructure_title'),
            'message' => __('borrower.guaranteed.notify.restructure_message', [
                'borrower'  => $application->customer?->legalDisplayName() ?? 'Borrower',
                'reference' => $loan->loan_number,
                'type'      => $type,
            ]),
        ]);
    }

    public function notifyTopUpRequested(Loan $loan, float $amount): void
    {
        $application = $loan->application ?? LoanApplication::find($loan->loan_application_id);
        if (! $application) {
            return;
        }

        $application->loadMissing(['customer']);
        $this->notifyGuarantors($application, 'guarantor_top_up_requested', [
            'title'   => __('borrower.guaranteed.notify.top_up_title'),
            'message' => __('borrower.guaranteed.notify.top_up_message', [
                'borrower'  => $application->customer?->legalDisplayName() ?? 'Borrower',
                'reference' => $loan->loan_number,
                'amount'    => format_money($amount),
            ]),
        ]);
    }

    /** @param array{title: string, message: string} $copy */
    private function notifyGuarantors(LoanApplication $application, string $template, array $copy): void
    {
        $link = $this->primaryLinkForApplication($application);

        foreach ($this->access->guarantorCustomersForApplication($application) as $guarantor) {
            $url = $link
                ? route('site.borrower.guaranteed.show', $link)
                : route('site.borrower.loans', ['tab' => 'guaranteed']);

            $this->notifications->notifyInApp(
                $guarantor,
                $copy['message'],
                'guarantor',
                $template,
                $copy['title'],
                $url,
            );
        }
    }

    private function primaryLinkForApplication(LoanApplication $application): ?CustomerGuarantor
    {
        return $this->access->approvedLinksForApplication($application)->first();
    }
}
