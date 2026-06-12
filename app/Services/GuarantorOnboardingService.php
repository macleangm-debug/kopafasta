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
            ->with(['borrower', 'application.product', 'customerGuarantor.guarantor'])
            ->first();
    }

    public function registrationPrefill(?GuarantorInvitation $invitation): ?array
    {
        if (! $invitation || $invitation->type !== 'external') {
            return null;
        }

        $invitation->loadMissing('customerGuarantor.guarantor', 'borrower');
        $guarantor = $invitation->customerGuarantor?->guarantor;

        $firstPart = trim((string) ($guarantor?->first_name ?? ''));
        $nameParts = preg_split('/\s+/', $firstPart, 2) ?: [];
        $firstName = $nameParts[0] ?? '';
        $middleName = trim($nameParts[1] ?? '');
        $lastName = trim((string) ($guarantor?->last_name ?? ''));

        $contact = trim((string) $invitation->contact);
        $phone = trim((string) ($guarantor?->phone ?? ''));
        $country = 'TZ';
        $dialCode = '+255';
        $localPhone = '';

        if ($phone === '' && $contact !== '' && ! str_contains($contact, '@')) {
            $phone = $contact;
        }

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

        $borrower = $invitation->borrower;

        return [
            'first_name'    => $firstName,
            'middle_name'   => $middleName,
            'last_name'     => $lastName,
            'phone'         => $phone !== '' ? app(GuarantorInvitationService::class)->normalizePhone($phone) : '',
            'country'       => $country,
            'dial_code'     => $dialCode,
            'local_phone'   => $localPhone,
            'borrower_name' => trim(($borrower->first_name ?? '').' '.($borrower->last_name ?? '')),
        ];
    }

    public function phoneMatchesInvitation(GuarantorInvitation $invitation, string $phone): bool
    {
        return app(PortalContextService::class)->contactMatchesCustomer(
            $invitation,
            new Customer(['phone' => $phone]),
        );
    }

    public function invitationFromSession(Request $request): ?GuarantorInvitation
    {
        return $this->findByToken($request->session()->get('guarantor_invite_token'));
    }

    public function pendingInvitationForCustomer(Customer $customer): ?GuarantorInvitation
    {
        $portal = app(PortalContextService::class);

        return $portal->pendingGuarantorInvitations($customer)
            ->first(fn (GuarantorInvitation $invitation) => $invitation->type === 'external');
    }

    public function resolveInvitation(Request $request, Customer $customer): ?GuarantorInvitation
    {
        $portal = app(PortalContextService::class);

        if ($sessionInvitation = $this->invitationFromSession($request)) {
            if ($portal->isBorrowerForInvitation($sessionInvitation, $customer)) {
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

    public function rememberInvitation(Request $request, GuarantorInvitation $invitation): void
    {
        $request->session()->put('guarantor_invite_token', $invitation->token);
    }

    public function forgetInvitation(Request $request): void
    {
        $request->session()->forget('guarantor_invite_token');
    }

    public function linkInvitee(GuarantorInvitation $invitation, Customer $customer, bool $fromTrustedSession = false): void
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

        if (! $fromTrustedSession
            && ! $invitation->guarantor_customer_id
            && ! $portal->canActAsGuarantorFor($invitation, $customer)) {
            throw new \InvalidArgumentException('This invitation does not match your account details.');
        }

        $invitation->update(['guarantor_customer_id' => $customer->id]);

        if ($guarantor = $invitation->customerGuarantor?->guarantor) {
            $guarantor->update([
                'first_name' => trim(collect([$customer->first_name, $customer->middle_name])->filter()->implode(' ')),
                'last_name'  => $customer->last_name ?? $guarantor->last_name,
                'phone'      => $customer->phone ?? $guarantor->phone,
                'email'      => $customer->email ?: null,
                'national_id'=> $customer->national_id ?? $guarantor->national_id,
            ]);
        }
    }

    public function coreRequirementsMet(Customer $customer): bool
    {
        return app(ApplicationRequirementsService::class)->checklist($customer)['can_apply'];
    }

    public function guarantorRequirementsMet(Customer $customer): bool
    {
        if (! $this->coreRequirementsMet($customer)) {
            return false;
        }

        return app(ProfileCompletionService::class)->isFullyComplete($customer);
    }

    /** @return array{met: bool, percent: int, checklist: array<string, mixed>, next_url: string|null} */
    public function guarantorProfileStatus(Customer $customer): array
    {
        $checklist = app(ApplicationRequirementsService::class)->checklist($customer);
        $percent = app(ProfileCompletionService::class)->calculate($customer)['percent'] ?? 0;

        return [
            'met'        => $checklist['can_apply'] && $percent >= 100,
            'percent'    => $percent,
            'checklist'  => $checklist,
            'next_url'   => app(ProfileWizardService::class)->resumeUrl($customer),
        ];
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

        return $this->guarantorRequirementsMet($customer);
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
            if ($request->routeIs('site.membership.*', 'site.borrower.setup-pin', 'site.borrower.setup-pin.post')) {
                return null;
            }

            return redirect()->route('site.membership.renew')
                ->with('status', __('borrower.guarantor_invite.continue_after_membership'));
        }

        if (! $this->guarantorRequirementsMet($customer)) {
            $status = app(ProfileCompletionService::class)->calculate($customer);
            $url = app(ProfileWizardService::class)->resumeUrl($customer);

            return redirect()->to($url)
                ->with('status', __('borrower.guarantor_invite.continue_after_profile', ['percent' => $status['percent'] ?? 0]));
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
        $invitation = $this->resolveInvitation($request, $customer);
        if (! $invitation) {
            return null;
        }

        if ($invitation->customerGuarantor?->status === 'approved') {
            $this->forgetInvitation($request);

            return null;
        }

        return $this->redirectToContinue($request, $customer, $invitation);
    }

    private function customerOwnsInvitation(GuarantorInvitation $invitation, Customer $customer): bool
    {
        $portal = app(PortalContextService::class);

        if ($portal->isBorrowerForInvitation($invitation, $customer)) {
            return false;
        }

        if ($invitation->guarantor_customer_id) {
            return (int) $invitation->guarantor_customer_id === (int) $customer->id;
        }

        return $portal->canActAsGuarantorFor($invitation, $customer);
    }
}
