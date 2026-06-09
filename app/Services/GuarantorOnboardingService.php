<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GuarantorInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GuarantorOnboardingService
{
    public function findByToken(?string $token): ?GuarantorInvitation
    {
        if (blank($token)) {
            return null;
        }

        return GuarantorInvitation::query()
            ->where('token', $token)
            ->where('type', 'external')
            ->with(['borrower', 'application.product', 'customerGuarantor'])
            ->first();
    }

    public function invitationFromSession(Request $request): ?GuarantorInvitation
    {
        return $this->findByToken($request->session()->get('guarantor_invite_token'));
    }

    public function rememberInvitation(Request $request, GuarantorInvitation $invitation): void
    {
        $request->session()->put('guarantor_invite_token', $invitation->token);
    }

    public function forgetInvitation(Request $request): void
    {
        $request->session()->forget('guarantor_invite_token');
    }

    public function linkInvitee(GuarantorInvitation $invitation, Customer $customer): void
    {
        if ($invitation->type !== 'external') {
            return;
        }

        $portal = app(PortalContextService::class);
        if ($portal->isBorrowerForInvitation($invitation, $customer)) {
            throw new \InvalidArgumentException('You cannot guarantee your own loan application.');
        }

        if ($invitation->guarantor_customer_id
            && (int) $invitation->guarantor_customer_id !== (int) $customer->id) {
            throw new \InvalidArgumentException('This invitation is linked to another account.');
        }

        if (! $invitation->guarantor_customer_id && ! $portal->canActAsGuarantorFor($invitation, $customer)) {
            throw new \InvalidArgumentException('This invitation does not match your account details.');
        }

        $invitation->update(['guarantor_customer_id' => $customer->id]);

        if ($guarantor = $invitation->customerGuarantor?->guarantor) {
            $guarantor->update([
                'first_name' => $customer->first_name ?? $guarantor->first_name,
                'last_name'  => $customer->last_name ?? $guarantor->last_name,
                'phone'      => $customer->phone ?? $guarantor->phone,
                'email'      => $customer->email ?? $guarantor->email,
                'national_id'=> $customer->national_id ?? $guarantor->national_id,
            ]);
        }
    }

    public function coreRequirementsMet(Customer $customer): bool
    {
        return app(ApplicationRequirementsService::class)->checklist($customer)['can_apply'];
    }

    public function canFinalize(Customer $customer, GuarantorInvitation $invitation): bool
    {
        if ($invitation->type !== 'external') {
            return false;
        }

        if (! in_array($invitation->status, ['accepted', 'pending'], true)) {
            return false;
        }

        if ($invitation->guarantor_customer_id && $invitation->guarantor_customer_id !== $customer->id) {
            return false;
        }

        $link = $invitation->customerGuarantor;
        if (! $link || $link->status === 'approved') {
            return false;
        }

        return $this->coreRequirementsMet($customer);
    }

    public function redirectToContinue(Request $request, Customer $customer, GuarantorInvitation $invitation): ?RedirectResponse
    {
        if ($invitation->type !== 'external' || ! in_array($invitation->status, ['accepted', 'pending'], true)) {
            return null;
        }

        $portal = app(PortalContextService::class);
        if ($portal->isBorrowerForInvitation($invitation, $customer)) {
            $this->forgetInvitation($request);

            return redirect()->route('site.borrower.loans', ['tab' => 'applications'])
                ->with('error', 'This guarantor link belongs to someone else.');
        }

        try {
            $this->linkInvitee($invitation, $customer);
        } catch (\InvalidArgumentException $e) {
            $this->forgetInvitation($request);

            return redirect()->route('site.borrower.dashboard')->with('error', $e->getMessage());
        }

        $user = $customer->user;
        if ($user && ! app(\App\Services\PinService::class)->hasPin($user)) {
            return redirect()->route('site.borrower.setup-pin')
                ->with('status', __('borrower.guarantor_invite.continue_after_pin'));
        }

        if (! $customer->hasMembership()) {
            return redirect()->route('site.membership.renew')
                ->with('status', __('borrower.guarantor_invite.continue_after_membership'));
        }

        if (! $this->coreRequirementsMet($customer)) {
            $checklist = app(ApplicationRequirementsService::class)->checklist($customer);
            $url = $checklist['first_action_url'] ?? route('site.borrower.profile');

            return redirect()->to($url)
                ->with('status', __('borrower.guarantor_invite.continue_after_profile'));
        }

        if ($this->canFinalize($customer, $invitation->fresh())) {
            return redirect()->route('site.guarantor.onboarding')
                ->with('status', __('borrower.guarantor_invite.continue_final_step'));
        }

        return redirect()->route('site.borrower.dashboard')
            ->with('status', __('borrower.guarantor_invite.continue_in_portal'));
    }

    public function finalize(GuarantorInvitation $invitation, Customer $customer, Request $request): void
    {
        if (! $this->canFinalize($customer, $invitation)) {
            throw new \InvalidArgumentException('Guarantor onboarding is not ready to complete.');
        }

        $this->linkInvitee($invitation, $customer);

        $link = $invitation->customerGuarantor;
        if ($link) {
            app(GuarantorInvitationService::class)->approve($link);
        }

        $this->forgetInvitation($request);

        $borrower = $invitation->borrower;
        if ($borrower) {
            $guarantorName = trim($customer->first_name.' '.$customer->last_name);
            app(NotificationService::class)->notifyInApp(
                $borrower,
                "{$guarantorName} completed guarantor onboarding for your loan application.",
                'guarantor',
                'guarantor_onboarded',
            );
        }
    }

    public function redirectIfPending(Request $request, Customer $customer): ?RedirectResponse
    {
        $invitation = $this->invitationFromSession($request);
        if (! $invitation) {
            return null;
        }

        $portal = app(PortalContextService::class);

        if ($portal->isBorrowerForInvitation($invitation, $customer)) {
            $this->forgetInvitation($request);

            return null;
        }

        if ($invitation->guarantor_customer_id && (int) $invitation->guarantor_customer_id !== (int) $customer->id) {
            $this->forgetInvitation($request);

            return null;
        }

        if (! $invitation->guarantor_customer_id && ! $portal->canActAsGuarantorFor($invitation, $customer)) {
            $this->forgetInvitation($request);

            return null;
        }

        if ($invitation->customerGuarantor?->status === 'approved') {
            $this->forgetInvitation($request);

            return null;
        }

        return $this->redirectToContinue($request, $customer, $invitation);
    }
}
