<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\GuarantorInvitation;
use App\Services\GuarantorInvitationService;
use App\Services\GuarantorOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicGuarantorController extends Controller
{
    use AuditsActions;

    public function show(string $token): View|RedirectResponse
    {
        $invitation = GuarantorInvitation::query()
            ->where('token', $token)
            ->with(['borrower', 'application.product'])
            ->firstOrFail();

        if ($invitation->isExpired() && $invitation->isPending()) {
            $invitation->update(['status' => 'expired']);

            return view('site.guarantor.expired', compact('invitation'));
        }

        if (! $invitation->isPending()) {
            return view('site.guarantor.responded', compact('invitation'));
        }

        return view('site.guarantor.show', compact('invitation'));
    }

    public function accept(Request $request, string $token, GuarantorInvitationService $service, GuarantorOnboardingService $onboarding): RedirectResponse
    {
        $invitation = GuarantorInvitation::query()->where('token', $token)->firstOrFail();

        if (! $invitation->isPending() || $invitation->isExpired()) {
            return back()->with('error', 'This invitation is no longer active.');
        }

        if ($invitation->type === 'internal' && $invitation->guarantor_customer_id) {
            $link = $invitation->customerGuarantor;
            if ($link) {
                $service->approve($link);
            }
        } elseif ($invitation->type === 'external') {
            $onboarding->rememberInvitation($request, $invitation);
            $invitation->update([
                'status'       => 'accepted',
                'responded_at' => now(),
            ]);

            if (auth()->check() && auth()->user()->customer) {
                $onboarding->linkInvitee($invitation, auth()->user()->customer);

                if ($onboarding->canFinalize(auth()->user()->customer, $invitation)) {
                    return redirect()->route('site.guarantor.onboarding')
                        ->with('status', 'Complete the final step to become a guarantor.');
                }

                return redirect()->route('site.borrower.dashboard')
                    ->with('status', 'Invitation accepted. Pay your registration fee and complete your profile to finalize your guarantor role.');
            }

            return redirect()
                ->route('site.register.borrower')
                ->with('status', 'Invitation accepted. Create your KopaFasta account to complete guarantor onboarding.');
        } else {
            $invitation->update([
                'status'       => 'accepted',
                'responded_at' => now(),
            ]);
        }

        $this->auditBorrower('guarantor_invitation.accepted', $invitation, [
            'application_id' => $invitation->loan_application_id,
        ]);

        return redirect()
            ->route('site.guarantor.show', $token)
            ->with('status', 'Thank you. Your acceptance has been recorded.');
    }

    public function reject(Request $request, string $token, GuarantorInvitationService $service): RedirectResponse
    {
        $invitation = GuarantorInvitation::query()->where('token', $token)->firstOrFail();

        if (! $invitation->isPending() || $invitation->isExpired()) {
            return back()->with('error', 'This invitation is no longer active.');
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

        return redirect()
            ->route('site.guarantor.show', $token)
            ->with('status', 'Invitation declined.');
    }
}
