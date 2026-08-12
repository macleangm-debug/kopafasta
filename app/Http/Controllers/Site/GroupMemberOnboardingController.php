<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Services\BorrowerSignatureService;
use App\Services\GroupMemberApplicationService;
use App\Services\GroupMemberOnboardingService;
use App\Services\GroupMemberSignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupMemberOnboardingController extends Controller
{
    use AuditsActions;

    public function show(
        Request $request,
        GroupMemberOnboardingService $onboarding,
        GroupMemberApplicationService $applications,
        GroupMemberSignatureService $signatures,
        BorrowerSignatureService $borrowerSignatures,
    ): View|RedirectResponse {
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

        $view = $applications->buildViewModel($invitation, $customer);
        $profileSignature = $borrowerSignatures->profileSignature($customer);
        if (! $profileSignature) {
            return redirect()->route('site.borrower.profile', ['section' => 'personal', 'focus' => 'signature'])
                ->with('warning', __('borrower.apply.group.profile_signature_required'));
        }

        $carousel = $signatures->carouselForDraftMembers($view['members'] ?? [], $customer);

        return view('site.group-member.onboarding', [
            'invitation' => $invitation,
            'customer' => $customer,
            'profileSignature' => $profileSignature,
            'signatureCarousel' => $carousel,
            'group_name' => $view['group_name'] ?? $invitation->group_name,
        ]);
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

        $request->validate([
            'consent' => ['accepted'],
        ]);

        try {
            $signatures->confirmFromProfile($invitation, $customer);
            $onboarding->finalize($invitation->fresh(), $customer, $request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('site.borrower.profile', ['section' => 'personal', 'focus' => 'signature'])
                ->with('warning', $e->errors()['signature_data'][0] ?? __('borrower.apply.group.profile_signature_required'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('site.borrower.dashboard')->with('error', $e->getMessage());
        }

        $this->auditBorrower('group_member_onboarding.completed', $invitation, [
            'leader_customer_id' => $invitation->leader_customer_id,
            'signature_source' => 'profile',
        ]);

        return redirect()->route('site.borrower.dashboard')
            ->with('status', __('borrower.apply.group.onboarding_complete'));
    }
}
