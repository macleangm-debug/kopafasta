<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Services\ApplicationOfferService;
use App\Services\LoanAgreementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        try {
            $agreement = $this->service->generateLoanContract($loan_application, regenerate: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('admin.loan-applications.guided-post-approval', $loan_application)
                ->with('error', collect($e->errors())->flatten()->first() ?: 'Agreement not ready.');
        }

        return redirect()
            ->route('admin.loan-applications.guided-post-approval', $loan_application)
            ->with('status', "Loan contract generated ({$agreement->reference}).");
    }

    public function regenerateRejectionLetter(LoanApplication $loan_application): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.view'), 403);
        abort_unless(
            in_array((string) $loan_application->status, ['rejected'], true)
            || (string) $loan_application->current_stage === 'rejected',
            404
        );

        try {
            $agreement = $this->service->generateRejectionLetter(
                $loan_application->loadMissing(['customer', 'product']),
                regenerate: true,
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.loan-applications.show', $loan_application)
                ->with('error', collect($e->errors())->flatten()->first() ?: 'Decision letter assets are incomplete.');
        }

        return redirect()
            ->route('admin.loan-applications.show', $loan_application)
            ->with('status', "Decision letter regenerated ({$agreement->reference}). The borrower was not notified automatically.");
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
