<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Services\LoanAgreementService;

class LoanAgreementController extends Controller
{
    public function __construct(private readonly LoanAgreementService $service) {}

    public function generate(LoanApplication $application)
    {
        $agreement = $this->service->generateOfferLetter($application, regenerate: true);

        return redirect()
            ->route('admin.loan-applications.show', $application)
            ->with('status', "Offer letter generated ({$agreement->reference}).");
    }
}
