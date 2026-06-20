<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GroupMemberInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupMemberOnboardingService
{
    public function findByToken(?string $token): ?GroupMemberInvitation
    {
        if (blank($token)) {
            return null;
        }

        return GroupMemberInvitation::query()
            ->where('token', $token)
            ->with(['leader', 'product'])
            ->first();
    }

    public function registrationPrefill(?GroupMemberInvitation $invitation): ?array
    {
        if (! $invitation) {
            return null;
        }

        $invitation->loadMissing('leader');

        $firstName = trim((string) $invitation->invitee_first_name);
        $middleName = trim((string) ($invitation->invitee_middle_name ?? ''));
        $lastName = trim((string) $invitation->invitee_last_name);
        $phone = trim((string) $invitation->invitee_phone);
        $country = 'TZ';
        $dialCode = '+255';
        $localPhone = '';

        if ($phone !== '') {
            $digits = preg_replace('/\D/', '', $phone);
            if (str_starts_with($digits, '254')) {
                $country = 'KE';
                $dialCode = '+254';
                $localPhone = ltrim(substr($digits, 3), '0');
            } elseif (str_starts_with($digits, '256')) {
                $country = 'UG';
                $dialCode = '+256';
                $localPhone = ltrim(substr($digits, 3), '0');
            } else {
                if (str_starts_with($digits, '255')) {
                    $digits = substr($digits, 3);
                }
                $localPhone = ltrim($digits, '0');
            }
        }

        $leader = $invitation->leader;

        return [
            'first_name'    => $firstName,
            'middle_name'   => $middleName,
            'last_name'     => $lastName,
            'phone'         => $phone !== '' ? app(GuarantorInvitationService::class)->normalizePhone($phone) : '',
            'country'       => $country,
            'dial_code'     => $dialCode,
            'local_phone'   => $localPhone,
            'borrower_name' => trim(($leader->first_name ?? '').' '.($leader->last_name ?? '')),
        ];
    }

    public function phoneMatchesInvitation(GroupMemberInvitation $invitation, string $phone): bool
    {
        $normalizedInvite = app(GuarantorInvitationService::class)->normalizePhone($invitation->invitee_phone);
        $normalizedInput = app(GuarantorInvitationService::class)->normalizePhone($phone);

        if ($normalizedInvite === '' || $normalizedInput === '') {
            return false;
        }

        if ($normalizedInvite === $normalizedInput) {
            return true;
        }

        $inviteDigits = preg_replace('/\D/', '', $normalizedInvite) ?? '';
        $inputDigits = preg_replace('/\D/', '', $normalizedInput) ?? '';

        return strlen($inviteDigits) >= 9 && strlen($inputDigits) >= 9
            && substr($inviteDigits, -9) === substr($inputDigits, -9);
    }

    public function invitationFromSession(Request $request): ?GroupMemberInvitation
    {
        return $this->findByToken($request->session()->get('group_member_invite_token'));
    }

    public function pendingInvitationForCustomer(Customer $customer): ?GroupMemberInvitation
    {
        $linked = GroupMemberInvitation::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->latest('id')
            ->first();

        if ($linked) {
            return $linked;
        }

        $phone = app(GuarantorInvitationService::class)->normalizePhone($customer->phone ?? '');

        return GroupMemberInvitation::query()
            ->whereNull('customer_id')
            ->whereIn('status', ['pending', 'accepted'])
            ->where(function ($query) use ($customer, $phone): void {
                if ($phone !== '') {
                    $query->where('invitee_phone', $phone);
                }
                if ($customer->email) {
                    $query->orWhere('invitee_email', $customer->email);
                }
            })
            ->latest('id')
            ->get()
            ->first(fn (GroupMemberInvitation $invitation) => $this->phoneMatchesInvitation($invitation, $customer->phone ?? ''));
    }

    public function resolveInvitation(Request $request, Customer $customer): ?GroupMemberInvitation
    {
        if ($sessionInvitation = $this->invitationFromSession($request)) {
            if ($this->isLeaderForInvitation($sessionInvitation, $customer)) {
                $this->forgetInvitation($request);
            } elseif ($this->customerOwnsInvitation($sessionInvitation, $customer)) {
                return $sessionInvitation;
            } else {
                $this->forgetInvitation($request);
            }
        }

        if ($invitation = $this->pendingInvitationForCustomer($customer)) {
            $this->rememberInvitation($request, $invitation);

            return $invitation;
        }

        return null;
    }

    public function rememberInvitation(Request $request, GroupMemberInvitation $invitation): void
    {
        $request->session()->put('group_member_invite_token', $invitation->token);
    }

    public function forgetInvitation(Request $request): void
    {
        $request->session()->forget('group_member_invite_token');
    }

    public function seedInvitationFromQuery(Request $request): void
    {
        $token = $request->query('group_invite');
        if (! is_string($token) || blank($token)) {
            return;
        }

        if ($invitation = $this->findByToken($token)) {
            $this->rememberInvitation($request, $invitation);
        }
    }

    public function linkInvitee(GroupMemberInvitation $invitation, Customer $customer, bool $fromTrustedSession = false): void
    {
        if ($this->isLeaderForInvitation($invitation, $customer)) {
            throw new \InvalidArgumentException(__('borrower.apply.group.lookup_self'));
        }

        if ($invitation->customer_id && (int) $invitation->customer_id !== (int) $customer->id) {
            throw new \InvalidArgumentException(__('borrower.apply.group.invite_linked_other'));
        }

        if (! $fromTrustedSession
            && ! $invitation->customer_id
            && ! $this->phoneMatchesInvitation($invitation, $customer->phone ?? '')) {
            throw new \InvalidArgumentException(__('borrower.apply.group.invite_phone_mismatch'));
        }

        $invitation->update(['customer_id' => $customer->id]);
    }

    public function memberRequirementsMet(Customer $customer): bool
    {
        if (! app(ApplicationRequirementsService::class)->checklist($customer)['can_apply']) {
            return false;
        }

        return app(ProfileCompletionService::class)->isFullyComplete($customer);
    }

    public function canFinalize(Customer $customer, GroupMemberInvitation $invitation): bool
    {
        if (! in_array($invitation->status, ['accepted', 'pending'], true)) {
            return false;
        }

        if ($invitation->customer_id && (int) $invitation->customer_id !== (int) $customer->id) {
            return false;
        }

        if ($invitation->status === 'completed') {
            return false;
        }

        return $this->memberRequirementsMet($customer);
    }

    public function redirectToContinue(Request $request, Customer $customer, GroupMemberInvitation $invitation): ?RedirectResponse
    {
        if (! in_array($invitation->status, ['accepted', 'pending'], true)) {
            return null;
        }

        if ($this->isLeaderForInvitation($invitation, $customer)) {
            $this->forgetInvitation($request);

            return redirect()->route('site.borrower.dashboard')
                ->with('error', __('borrower.apply.group.invite_leader_cannot_join'));
        }

        try {
            $this->linkInvitee($invitation, $customer);
        } catch (\InvalidArgumentException $e) {
            $this->forgetInvitation($request);

            return redirect()->route('site.borrower.dashboard')->with('error', $e->getMessage());
        }

        $user = $customer->user;
        if ($user && ! app(PinService::class)->hasPin($user)) {
            return redirect()->route('site.borrower.setup-pin')
                ->with('status', __('borrower.apply.group.continue_after_pin'));
        }

        if (! $customer->hasMembership()) {
            if ($request->routeIs('site.membership.*', 'site.borrower.setup-pin', 'site.borrower.setup-pin.post')) {
                return null;
            }

            return redirect()->route('site.membership.renew')
                ->with('status', __('borrower.apply.group.continue_after_membership'));
        }

        if (! $this->memberRequirementsMet($customer)) {
            $status = app(ProfileCompletionService::class)->calculate($customer);
            $url = app(ProfileWizardService::class)->resumeUrl($customer);

            return redirect()->to($url)
                ->with('status', __('borrower.apply.group.continue_after_profile', ['percent' => $status['percent'] ?? 0]));
        }

        if ($this->canFinalize($customer, $invitation->fresh())) {
            return redirect()->route('site.group-member.onboarding')
                ->with('status', __('borrower.apply.group.continue_final_step'));
        }

        return redirect()->route('site.borrower.dashboard')
            ->with('status', __('borrower.apply.group.continue_in_portal'));
    }

    public function finalize(GroupMemberInvitation $invitation, Customer $customer, Request $request): void
    {
        if (! $this->canFinalize($customer, $invitation)) {
            throw new \InvalidArgumentException(__('borrower.apply.group.onboarding_not_ready'));
        }

        $this->linkInvitee($invitation, $customer);

        $invitation->update([
            'status'       => 'completed',
            'responded_at' => $invitation->responded_at ?? now(),
        ]);

        $this->forgetInvitation($request);

        $leader = $invitation->leader;
        if ($leader) {
            app(NotificationService::class)->notifyInApp(
                $leader,
                __('borrower.apply.group.member_onboarded_notice', ['name' => $customer->full_name]),
                'group_loan',
                'group_member_onboarded',
            );
        }
    }

    public function redirectIfPending(Request $request, Customer $customer): ?RedirectResponse
    {
        $invitation = $this->resolveInvitation($request, $customer);
        if (! $invitation) {
            return null;
        }

        if ($invitation->status === 'completed') {
            $this->forgetInvitation($request);

            return null;
        }

        return $this->redirectToContinue($request, $customer, $invitation);
    }

    public function isLeaderForInvitation(GroupMemberInvitation $invitation, Customer $customer): bool
    {
        return (int) $invitation->leader_customer_id === (int) $customer->id;
    }

    private function customerOwnsInvitation(GroupMemberInvitation $invitation, Customer $customer): bool
    {
        if ($this->isLeaderForInvitation($invitation, $customer)) {
            return false;
        }

        if ($invitation->customer_id) {
            return (int) $invitation->customer_id === (int) $customer->id;
        }

        return $this->phoneMatchesInvitation($invitation, $customer->phone ?? '');
    }
}
