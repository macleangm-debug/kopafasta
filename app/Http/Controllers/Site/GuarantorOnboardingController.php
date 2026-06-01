<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Services\GuarantorOnboardingService;
use App\Services\GuarantorSignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuarantorOnboardingController extends Controller
{
    use AuditsActions;

    public function show(Request $request, GuarantorOnboardingService $onboarding): View|RedirectResponse
    {
        $customer = $request->user()?->customer;
        if (! $customer) {
            return redirect()->route('site.borrower.dashboard');
        }

        $invitation = $onboarding->invitationFromSession($request);
        if (! $invitation) {
            return redirect()->route('site.borrower.dashboard')
                ->with('status', 'No pending guarantor invitation found.');
        }

        if (! $onboarding->canFinalize($customer, $invitation)) {
            return redirect()->route('site.borrower.dashboard')
                ->with('warning', 'Complete identity verification and profile requirements before finalizing your guarantor role.');
        }

        return view('site.guarantor.onboarding', compact('invitation', 'customer'));
    }

    public function complete(Request $request, GuarantorOnboardingService $onboarding, GuarantorSignatureService $signatures): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_unless($customer, 403);

        $invitation = $onboarding->invitationFromSession($request);
        abort_unless($invitation, 404);

        $data = $request->validate([
            'signer_name'    => ['required', 'string', 'max:120'],
            'signature_data' => ['required', 'string', 'starts_with:data:image/png;base64,'],
            'consent'        => ['accepted'],
        ]);

        $application = $invitation->application;
        abort_unless($application, 422);

        try {
            $signatures->record(
                $application,
                $data['signer_name'],
                $data['signature_data'],
                $invitation->customerGuarantor,
                $invitation,
            );
            $onboarding->finalize($invitation, $customer, $request);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('site.borrower.dashboard')->with('error', $e->getMessage());
        }

        $this->auditBorrower('guarantor_onboarding.completed', $invitation, [
            'application_id' => $invitation->loan_application_id,
        ]);

        return redirect()->route('site.borrower.loans')
            ->with('status', 'You are now an approved guarantor for this loan application.');
    }
}
