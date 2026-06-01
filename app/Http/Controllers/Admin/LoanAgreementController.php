<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Services\LoanAgreementService;

class LoanAgreementController extends Controller
{
    public function __construct(private readonly LoanAgreementService $service) {}

    public function generate(LoanApplication $loan_application)
    {
        $agreement = $this->service->generateOfferLetter($loan_application, regenerate: true);

        return redirect()
            ->route('admin.loan-applications.show', $loan_application)
            ->with('status', "Offer letter generated ({$agreement->reference}).");
    }
}
