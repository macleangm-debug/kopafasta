<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\GuarantorInvitation;
use App\Services\GuarantorInvitationService;
use App\Services\GuarantorOnboardingService;
use App\Services\PortalContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicGuarantorController extends Controller
{
    use AuditsActions;

    public function show(string $token, Request $request, GuarantorOnboardingService $onboarding): View|RedirectResponse
    {
        $invitation = GuarantorInvitation::query()
            ->where('token', $token)
            ->with(['borrower', 'application.product', 'customerGuarantor'])
            ->firstOrFail();

        if ($invitation->isExpired() && $invitation->isPending()) {
            $invitation->update(['status' => 'expired']);

            return view('site.guarantor.expired', compact('invitation'));
        }

        if ($invitation->status === 'rejected') {
            return view('site.guarantor.declined', compact('invitation'));
        }

        if ($invitation->status === 'accepted') {
            return $this->showAccepted($request, $invitation, $onboarding);
        }

        if ($invitation->status !== 'pending') {
            return view('site.guarantor.responded', compact('invitation'));
        }

        if ($invitation->type === 'internal' && ! auth()->check()) {
            session(['login_redirect' => route('site.guarantor.show', $invitation->token)]);

            return view('site.guarantor.member-login', compact('invitation'));
        }

        if ($invitation->type === 'internal' && auth()->user()?->customer) {
            $customer = auth()->user()->customer;
            if (app(PortalContextService::class)->canActAsGuarantorFor($invitation, $customer)) {
                return redirect()->route('site.borrower.loans', ['tab' => 'guarantor'])
                    ->with('status', __('borrower.guarantor_invite.member_view_request'));
            }
        }

        return view('site.guarantor.show', compact('invitation'));
    }

    public function declined(string $token): View
    {
        $invitation = GuarantorInvitation::query()
            ->where('token', $token)
            ->with(['borrower'])
            ->firstOrFail();

        return view('site.guarantor.declined', compact('invitation'));
    }

    public function accept(
        Request $request,
        string $token,
        GuarantorInvitationService $service,
        GuarantorOnboardingService $onboarding,
    ): RedirectResponse {
        $invitation = GuarantorInvitation::query()
            ->where('token', $token)
            ->with(['customerGuarantor'])
            ->firstOrFail();

        if (! $invitation->isPending() || $invitation->isExpired()) {
            return back()->with('error', __('borrower.guarantor_invite.no_longer_active'));
        }

        if ($invitation->type === 'internal') {
            if (! auth()->check()) {
                session(['login_redirect' => route('site.guarantor.show', $invitation->token)]);

                return redirect()
                    ->route('site.login')
                    ->with('status', __('borrower.guarantor_invite.login_to_respond'));
            }

            $customer = auth()->user()->customer;
            if (! $customer || ! app(PortalContextService::class)->canActAsGuarantorFor($invitation, $customer)) {
                return back()->with('error', __('borrower.guarantor_invite.wrong_account'));
            }

            if ($link = $invitation->customerGuarantor) {
                $service->approve($link);
            }

            $invitation->update([
                'status'       => 'accepted',
                'responded_at' => now(),
            ]);

            $this->auditBorrower('guarantor_invitation.accepted', $invitation, [
                'application_id' => $invitation->loan_application_id,
            ]);

            return redirect()->route('site.borrower.loans', ['tab' => 'guarantor'])
                ->with('status', __('borrower.guarantor_invite.member_accepted'));
        }

        $onboarding->rememberInvitation($request, $invitation);
        $invitation->update([
            'status'       => 'accepted',
            'responded_at' => now(),
        ]);

        $this->auditBorrower('guarantor_invitation.accepted', $invitation, [
            'application_id' => $invitation->loan_application_id,
        ]);

        if (! auth()->check()) {
            return redirect()
                ->route('site.register.borrower')
                ->with('status', __('borrower.guarantor_invite.create_account_prompt'));
        }

        $customer = auth()->user()->customer;
        if (! $customer) {
            return redirect()->route('site.register.borrower')
                ->with('status', __('borrower.guarantor_invite.create_account_prompt'));
        }

        if ($redirect = $onboarding->redirectToContinue($request, $customer, $invitation->fresh())) {
            return $redirect;
        }

        return redirect()->route('site.guarantor.show', $token)
            ->with('status', __('borrower.guarantor_invite.accept_recorded_continue'));
    }

    public function reject(Request $request, string $token, GuarantorInvitationService $service): RedirectResponse
    {
        $invitation = GuarantorInvitation::query()->where('token', $token)->firstOrFail();

        if (! $invitation->isPending() || $invitation->isExpired()) {
            return back()->with('error', __('borrower.guarantor_invite.no_longer_active'));
        }

        $notes = $request->validate(['notes' => ['nullable', 'string', 'max:500']])['notes'] ?? null;

        if ($link = $invitation->customerGuarantor) {
            $service->reject($link, $notes);
        } else {
            $invitation->update([
                'status'         => 'rejected',
                'responded_at'   => now(),
                'response_notes' => $notes,
            ]);
        }

        app(GuarantorOnboardingService::class)->forgetInvitation($request);

        $this->auditBorrower('guarantor_invitation.rejected', $invitation, [
            'application_id' => $invitation->loan_application_id,
        ]);

        return redirect()->route('site.guarantor.declined', $token);
    }

    private function showAccepted(
        Request $request,
        GuarantorInvitation $invitation,
        GuarantorOnboardingService $onboarding,
    ): View|RedirectResponse {
        $onboarding->rememberInvitation($request, $invitation);

        if ($invitation->type === 'internal') {
            if (! auth()->check()) {
                session(['login_redirect' => route('site.guarantor.show', $invitation->token)]);

                return view('site.guarantor.member-login', compact('invitation'));
            }

            $customer = auth()->user()->customer;
            if ($customer && app(PortalContextService::class)->canActAsGuarantorFor($invitation, $customer)) {
                return redirect()->route('site.borrower.loans', ['tab' => 'guarantor']);
            }

            return view('site.guarantor.member-login', compact('invitation'));
        }

        if (! auth()->check()) {
            return view('site.guarantor.accepted-continue', [
                'invitation' => $invitation,
                'cta_url'    => route('site.register.borrower'),
                'cta_label'  => __('borrower.guarantor_invite.create_account'),
            ]);
        }

        $customer = auth()->user()?->customer;
        if ($customer) {
            if ($redirect = $onboarding->redirectToContinue($request, $customer, $invitation)) {
                return $redirect;
            }

            if ($onboarding->canFinalize($customer, $invitation)) {
                return redirect()->route('site.guarantor.onboarding');
            }
        }

        return view('site.guarantor.accepted-continue', [
            'invitation' => $invitation,
            'cta_url'    => route('site.borrower.dashboard'),
            'cta_label'  => __('borrower.guarantor_invite.continue_guarantee'),
        ]);
    }
}
