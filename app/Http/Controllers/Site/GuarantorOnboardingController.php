<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Services\BorrowerSignatureService;
use App\Services\GuarantorOnboardingService;
use App\Services\GuarantorSignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuarantorOnboardingController extends Controller
{
    use AuditsActions;

    public function show(
        Request $request,
        GuarantorOnboardingService $onboarding,
        GuarantorSignatureService $signatures,
        BorrowerSignatureService $borrowerSignatures,
    ): View|RedirectResponse {
        $customer = $request->user()?->customer;
        if (! $customer) {
            return redirect()->route('site.borrower.dashboard');
        }

        $invitation = $onboarding->resolveInvitation($request, $customer);
        if (! $invitation) {
            return redirect()->route('site.borrower.dashboard')
                ->with('status', 'No pending guarantor invitation found.');
        }

        if (! $onboarding->canFinalize($customer, $invitation)) {
            return redirect()->route('site.borrower.profile')
                ->with('warning', __('borrower.guarantor.complete_profile'));
        }

        if (! $borrowerSignatures->profileSignature($customer)) {
            return redirect()->route('site.borrower.profile', ['section' => 'personal', 'focus' => 'signature'])
                ->with('warning', __('borrower.apply.group.profile_signature_required'));
        }

        // Accept + verified Profile signature = consent. Skip pad / gallery.
        try {
            $signatures->confirmFromProfile($invitation, $customer);
            $onboarding->finalize($invitation, $customer, $request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('site.borrower.profile', ['section' => 'personal', 'focus' => 'signature'])
                ->with('warning', $e->errors()['signature_data'][0] ?? __('borrower.apply.group.profile_signature_required'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('site.borrower.dashboard')->with('error', $e->getMessage());
        }

        $this->auditBorrower('guarantor_onboarding.completed', $invitation, [
            'application_id' => $invitation->loan_application_id,
            'signature_source' => 'profile',
            'auto' => true,
        ]);

        return redirect()->route('site.borrower.loans', ['tab' => 'guarantor'])
            ->with('status', 'You are now an approved guarantor for this loan application.');
    }

    public function complete(
        Request $request,
        GuarantorOnboardingService $onboarding,
        GuarantorSignatureService $signatures,
        BorrowerSignatureService $borrowerSignatures,
    ): RedirectResponse {
        $customer = $request->user()?->customer;
        abort_unless($customer, 403);

        $invitation = $onboarding->resolveInvitation($request, $customer);
        abort_unless($invitation, 404);

        if (! $borrowerSignatures->profileSignature($customer)) {
            return redirect()->route('site.borrower.profile', ['section' => 'personal', 'focus' => 'signature'])
                ->with('warning', __('borrower.apply.group.profile_signature_required'));
        }

        try {
            $signatures->confirmFromProfile($invitation, $customer);
            $onboarding->finalize($invitation, $customer, $request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('site.borrower.profile', ['section' => 'personal', 'focus' => 'signature'])
                ->with('warning', $e->errors()['signature_data'][0] ?? __('borrower.apply.group.profile_signature_required'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('site.borrower.dashboard')->with('error', $e->getMessage());
        }

        $this->auditBorrower('guarantor_onboarding.completed', $invitation, [
            'application_id' => $invitation->loan_application_id,
            'signature_source' => 'profile',
        ]);

        return redirect()->route('site.borrower.loans', ['tab' => 'guarantor'])
            ->with('status', 'You are now an approved guarantor for this loan application.');
    }
}
