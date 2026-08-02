<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\GroupMemberInvitation;
use App\Services\GroupMemberOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupMemberInviteController extends Controller
{
    use AuditsActions;

    public function show(string $token, Request $request, GroupMemberOnboardingService $onboarding): View|RedirectResponse
    {
        $invitation = GroupMemberInvitation::query()
            ->where('token', $token)
            ->with(['leader', 'product'])
            ->firstOrFail();

        if ($this->isExpired($invitation) && $invitation->status === 'pending') {
            $invitation->update(['status' => 'expired']);

            return view('site.group-member.expired', compact('invitation'));
        }

        if ($invitation->status === 'rejected') {
            return view('site.group-member.declined', compact('invitation'));
        }

        if (in_array($invitation->status, ['accepted', 'completed'], true)) {
            return $this->showAccepted($request, $invitation, $onboarding);
        }

        if ($invitation->status !== 'pending') {
            return view('site.group-member.responded', compact('invitation'));
        }

        if (! $invitation->link_opened_at) {
            $invitation->update(['link_opened_at' => now()]);
        }

        return view('site.group-member.invite', compact('invitation'));
    }

    public function accept(
        Request $request,
        string $token,
        GroupMemberOnboardingService $onboarding,
    ): RedirectResponse {
        $invitation = GroupMemberInvitation::query()
            ->where('token', $token)
            ->with(['leader'])
            ->firstOrFail();

        if ($invitation->status !== 'pending' || $this->isExpired($invitation)) {
            return back()->with('error', __('borrower.apply.group.invite_no_longer_active'));
        }

        $onboarding->rememberInvitation($request, $invitation);
        $invitation->update([
            'status'                  => 'accepted',
            'responded_at'            => now(),
            'registration_started_at' => $invitation->registration_started_at ?? now(),
        ]);

        $this->auditBorrower('group_member_invitation.accepted', $invitation, [
            'leader_customer_id' => $invitation->leader_customer_id,
        ]);

        $leader = $invitation->leader;
        if ($leader) {
            app(\App\Services\NotificationService::class)->notifyInApp(
                $leader,
                __('borrower.apply.group.member_accepted_notice', ['name' => $invitation->displayName()]),
                'group_loan',
                'group_member_accepted',
            );
        }

        if (! auth()->check()) {
            return redirect()->route('site.register.borrower');
        }

        $customer = auth()->user()?->customer;
        if (! $customer) {
            return redirect()->route('site.register.borrower');
        }

        if ($redirect = $onboarding->redirectToContinue($request, $customer, $invitation->fresh())) {
            return $redirect;
        }

        return redirect()->route('site.group-member.invite', $token)
            ->with('status', __('borrower.apply.group.accept_recorded'));
    }

    public function reject(Request $request, string $token, GroupMemberOnboardingService $onboarding): RedirectResponse
    {
        $invitation = GroupMemberInvitation::query()->where('token', $token)->firstOrFail();

        if ($invitation->status !== 'pending' || $this->isExpired($invitation)) {
            return back()->with('error', __('borrower.apply.group.invite_no_longer_active'));
        }

        $invitation->update([
            'status'       => 'rejected',
            'responded_at' => now(),
        ]);

        $onboarding->forgetInvitation($request);

        $leader = $invitation->leader;
        if ($leader) {
            app(\App\Services\GroupMemberInvitationService::class)
                ->removeInvitationFromLeaderDrafts($leader, (int) $invitation->id);

            app(\App\Services\NotificationService::class)->notifyInApp(
                $leader,
                __('borrower.apply.group.member_declined_notice', ['name' => $invitation->displayName()]),
                'group_loan',
                'group_member_declined',
            );
        }

        $this->auditBorrower('group_member_invitation.rejected', $invitation, [
            'leader_customer_id' => $invitation->leader_customer_id,
        ]);

        return redirect()->route('site.group-member.declined', $token);
    }

    public function declined(string $token): View
    {
        $invitation = GroupMemberInvitation::query()
            ->where('token', $token)
            ->with(['leader'])
            ->firstOrFail();

        return view('site.group-member.declined', compact('invitation'));
    }

    private function showAccepted(
        Request $request,
        GroupMemberInvitation $invitation,
        GroupMemberOnboardingService $onboarding,
    ): View|RedirectResponse {
        if (auth()->check() && ($customer = auth()->user()?->customer)) {
            if ($redirect = $onboarding->redirectToContinue($request, $customer, $invitation)) {
                return $redirect;
            }
        }

        return view('site.group-member.accepted', compact('invitation'));
    }

    private function isExpired(GroupMemberInvitation $invitation): bool
    {
        return $invitation->expires_at && $invitation->expires_at->isPast();
    }
}
