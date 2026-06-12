<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GuarantorInvitation;
use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Builder;

class PortalContextService
{
    /** Templates intended for guarantor inbox only — hidden from borrower notification views. */
    private const GUARANTOR_INBOX_TEMPLATES = [
        'guarantor_request',
        'guarantor_loan_approved',
        'guarantor_loan_disbursed',
        'guarantor_loan_arrears',
        'guarantor_loan_closed',
        'guarantor_restructure_requested',
        'guarantor_top_up_requested',
    ];

    /** Templates about guarantor activity shown to borrowers. */
    private const BORROWER_GUARANTOR_TEMPLATES = [
        'guarantor_onboarded',
        'guarantor_declined',
        'guarantor_sent',
        'guarantor_accepted',
    ];

    public function displayName(?Customer $customer): string
    {
        if (! $customer) {
            return (string) (auth()->user()?->name ?? 'Account');
        }

        return $customer->legalDisplayName() ?: (string) (auth()->user()?->name ?? 'Account');
    }

    public function isBorrowerForInvitation(GuarantorInvitation $invitation, Customer $customer): bool
    {
        return (int) $invitation->customer_id === (int) $customer->id;
    }

    public function canActAsGuarantorFor(GuarantorInvitation $invitation, Customer $customer): bool
    {
        if ($this->isBorrowerForInvitation($invitation, $customer)) {
            return false;
        }

        if ($invitation->guarantor_customer_id) {
            return (int) $invitation->guarantor_customer_id === (int) $customer->id;
        }

        return $this->contactMatchesCustomer($invitation, $customer);
    }

    public function contactMatchesCustomer(GuarantorInvitation $invitation, Customer $customer): bool
    {
        $contact = preg_replace('/\D+/', '', (string) $invitation->contact);
        $phone = preg_replace('/\D+/', '', (string) ($customer->phone ?? ''));

        if ($contact !== '' && $phone !== '') {
            if ($contact === $phone) {
                return true;
            }

            if (strlen($contact) >= 9 && strlen($phone) >= 9
                && substr($contact, -9) === substr($phone, -9)) {
                return true;
            }
        }

        $email = strtolower(trim((string) ($customer->email ?? $customer->user?->email ?? '')));
        $inviteEmail = strtolower(trim((string) $invitation->contact));

        return $email !== '' && $inviteEmail !== '' && $email === $inviteEmail;
    }

    public function pendingGuarantorInvitations(Customer $customer)
    {
        return GuarantorInvitation::query()
            ->with(['borrower', 'application.product', 'customerGuarantor'])
            ->where(function ($query) use ($customer) {
                $query->where('guarantor_customer_id', $customer->id)
                    ->orWhere(function ($inner) use ($customer) {
                        $inner->whereNull('guarantor_customer_id')
                            ->where('contact', '!=', '')
                            ->where(function ($contactQuery) use ($customer) {
                                if ($customer->phone) {
                                    $contactQuery->where('contact', $customer->phone);
                                }
                                if ($customer->email) {
                                    $contactQuery->orWhere('contact', $customer->email);
                                }
                            });
                    });
            })
            ->whereIn('status', ['pending', 'accepted'])
            ->whereHas('customerGuarantor', fn ($q) => $q->where('status', 'pending'))
            ->latest()
            ->get()
            ->filter(fn (GuarantorInvitation $invitation) => $this->canActAsGuarantorFor($invitation, $customer))
            ->values();
    }

    /** Pending guarantee links for the signed-in guarantor (reliable list rows). */
    public function pendingGuarantorLinks(Customer $customer)
    {
        $invitations = $this->pendingGuarantorInvitations($customer);
        $linkIds = $invitations->pluck('customer_guarantor_id')->filter()->unique()->values();

        if ($linkIds->isEmpty()) {
            return collect();
        }

        $links = \App\Models\CustomerGuarantor::query()
            ->with(['customer', 'application.product', 'invitation.borrower', 'invitation.application.product'])
            ->whereIn('id', $linkIds)
            ->where('status', 'pending')
            ->latest()
            ->get();

        return $links->map(function (\App\Models\CustomerGuarantor $link) use ($invitations) {
            $invitation = $link->invitation
                ?? $invitations->firstWhere('customer_guarantor_id', $link->id);
            $borrower = $invitation?->borrower ?? $link->customer;
            $application = $invitation?->application ?? $link->application;

            return (object) [
                'link'        => $link,
                'invitation'  => $invitation,
                'borrower'    => $borrower,
                'application' => $application,
            ];
        })->filter(fn ($row) => $row->invitation !== null)->values();
    }

    public function hasGuarantorWork(Customer $customer): bool
    {
        return $this->pendingGuarantorInvitations($customer)->isNotEmpty();
    }

    public function hasGuaranteedLoans(Customer $customer): bool
    {
        return app(GuaranteedLoanService::class)->linksForGuarantor($customer)->isNotEmpty();
    }

    public function borrowerNotificationsQuery(Customer $customer): Builder
    {
        return NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where(function ($query) {
                $query->whereNull('template')
                    ->orWhereNotIn('template', self::GUARANTOR_INBOX_TEMPLATES);
            });
    }

    public function guarantorNotificationsQuery(Customer $customer): Builder
    {
        return NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->whereIn('template', array_merge(self::GUARANTOR_INBOX_TEMPLATES, ['guarantor_action']));
    }
}
