<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Services\GroupMemberOnboardingService;
use App\Services\GroupMemberSignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupMemberOnboardingController extends Controller
{
    use AuditsActions;

    public function show(Request $request, GroupMemberOnboardingService $onboarding): View|RedirectResponse
    {
        $customer = $request->user()?->customer;
        if (! $customer) {
            return redirect()->route('site.borrower.dashboard');
        }

        $invitation = $onboarding->resolveInvitation($request, $customer);
        if (! $invitation) {
            return redirect()->route('site.borrower.dashboard')
                ->with('status', __('borrower.apply.group.no_pending_invite'));
        }

        if (! $onboarding->canFinalize($customer, $invitation)) {
            return redirect()->route('site.group-member.application')
                ->with('warning', __('borrower.apply.group.complete_profile_first'));
        }

        return view('site.group-member.onboarding', compact('invitation', 'customer'));
    }

    public function complete(
        Request $request,
        GroupMemberOnboardingService $onboarding,
        GroupMemberSignatureService $signatures,
    ): RedirectResponse {
        $customer = $request->user()?->customer;
        abort_unless($customer, 403);

        $invitation = $onboarding->resolveInvitation($request, $customer);
        abort_unless($invitation, 404);

        $data = $request->validate([
            'signer_name'    => ['required', 'string', 'max:120'],
            'signature_data' => ['required', 'string', 'starts_with:data:image/png;base64,'],
            'consent'        => ['accepted'],
        ]);

        try {
            $signatures->recordForInvitation(
                $invitation,
                $data['signer_name'],
                $data['signature_data'],
            );
            $onboarding->finalize($invitation->fresh(), $customer, $request);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('site.borrower.dashboard')->with('error', $e->getMessage());
        }

        $this->auditBorrower('group_member_onboarding.completed', $invitation, [
            'leader_customer_id' => $invitation->leader_customer_id,
        ]);

        return redirect()->route('site.borrower.dashboard')
            ->with('status', __('borrower.apply.group.onboarding_complete'));
    }
}
