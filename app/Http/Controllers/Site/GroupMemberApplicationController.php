<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\GroupMemberApplicationService;
use App\Services\GroupMemberOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupMemberApplicationController extends Controller
{
    public function show(Request $request, GroupMemberOnboardingService $onboarding, GroupMemberApplicationService $application): View|RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_unless($customer, 403);

        $invitation = $onboarding->resolveInvitation($request, $customer);
        if (! $invitation) {
            return redirect()->route('site.borrower.dashboard')
                ->with('error', __('borrower.apply.group.no_pending_invitation'));
        }

        if ($onboarding->isLeaderForInvitation($invitation, $customer)) {
            return redirect()->route('site.borrower.dashboard')
                ->with('error', __('borrower.apply.group.invite_leader_cannot_join'));
        }

        try {
            $onboarding->linkInvitee($invitation, $customer);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('site.borrower.dashboard')->with('error', $e->getMessage());
        }

        return view('site.group-member.application', $application->buildViewModel($invitation->fresh(), $customer));
    }
}
