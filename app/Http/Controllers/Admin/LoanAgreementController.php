<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Services\ApplicationOfferService;
use App\Services\LoanAgreementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoanAgreementController extends Controller
{
    public function __construct(
        private readonly LoanAgreementService $service,
        private readonly ApplicationOfferService $offers,
    ) {}

    public function generate(LoanApplication $loan_application)
    {
        if ($this->offers->offerDeclinedByBorrower($loan_application)) {
            return $this->resendOffer($loan_application);
        }

        $agreement = $this->service->generateOfferLetter($loan_application, regenerate: true);

        return redirect()
            ->route('admin.loan-applications.show', $loan_application)
            ->with('status', "Offer letter generated ({$agreement->reference}).");
    }

    public function generateContract(LoanApplication $loan_application)
    {
        $agreement = $this->service->generateLoanContract($loan_application, regenerate: true);

        return redirect()
            ->route('admin.loan-applications.show', $loan_application)
            ->with('status', "Loan contract regenerated ({$agreement->reference}).");
    }

    public function resendOffer(LoanApplication $loan_application): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.view'), 403);

        $this->offers->resendDeclinedOffer($loan_application, auth()->user());

        return redirect()
            ->route('admin.loan-applications.show', $loan_application)
            ->with('status', 'Offer resent to borrower with the same terms.');
    }

    public function reissueOffer(Request $request, LoanApplication $loan_application): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.view'), 403);

        $data = $request->validate([
            'offered_amount'       => ['required', 'numeric', 'min:0'],
            'offered_tenure_months'=> ['required', 'integer', 'min:1', 'max:120'],
            'remarks'              => ['nullable', 'string', 'max:1000'],
        ]);

        $this->offers->reissueDeclinedOffer(
            $loan_application,
            auth()->user(),
            (float) $data['offered_amount'],
            (int) $data['offered_tenure_months'],
            $data['remarks'] ?? null,
        );

        return redirect()
            ->route('admin.loan-applications.show', $loan_application)
            ->with('status', 'New offer issued to borrower.');
    }
}
