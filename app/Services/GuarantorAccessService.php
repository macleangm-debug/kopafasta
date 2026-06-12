<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\GuarantorInvitation;
use App\Models\Loan;
use App\Models\LoanApplication;

class GuarantorAccessService
{
    public function canViewGuarantee(Customer $guarantor, CustomerGuarantor $link): bool
    {
        if ($link->status !== 'approved') {
            return false;
        }

        $invitation = $this->invitationForLink($link);

        return $invitation !== null
            && app(PortalContextService::class)->canActAsGuarantorFor($invitation, $guarantor);
    }

    public function canViewLoan(Customer $guarantor, Loan $loan): bool
    {
        if (! $loan->loan_application_id) {
            return false;
        }

        return CustomerGuarantor::query()
            ->where('loan_application_id', $loan->loan_application_id)
            ->where('status', 'approved')
            ->get()
            ->contains(fn (CustomerGuarantor $link) => $this->canViewGuarantee($guarantor, $link));
    }

    public function invitationForLink(CustomerGuarantor $link): ?GuarantorInvitation
    {
        if ($link->relationLoaded('invitation') && $link->invitation) {
            return $link->invitation;
        }

        return GuarantorInvitation::query()
            ->where('customer_guarantor_id', $link->id)
            ->first();
    }

    /** @return \Illuminate\Support\Collection<int, CustomerGuarantor> */
    public function approvedLinksForApplication(LoanApplication $application)
    {
        return CustomerGuarantor::query()
            ->with('invitation')
            ->where('loan_application_id', $application->id)
            ->where('status', 'approved')
            ->get()
            ->filter(fn (CustomerGuarantor $link) => $this->invitationForLink($link)?->guarantor_customer_id)
            ->values();
    }

    /** @return \Illuminate\Support\Collection<int, Customer> */
    public function guarantorCustomersForApplication(LoanApplication $application)
    {
        $fromLinks = $this->approvedLinksForApplication($application)
            ->map(fn (CustomerGuarantor $link) => Customer::find($this->invitationForLink($link)?->guarantor_customer_id))
            ->filter();

        $fromInvitations = GuarantorInvitation::query()
            ->where('loan_application_id', $application->id)
            ->where('status', 'accepted')
            ->whereNotNull('guarantor_customer_id')
            ->whereHas('customerGuarantor', fn ($q) => $q->where('status', 'approved'))
            ->pluck('guarantor_customer_id');

        return $fromLinks
            ->merge(Customer::query()->whereIn('id', $fromInvitations)->get())
            ->unique('id')
            ->values();
    }

    public function guarantorCustomerForLink(CustomerGuarantor $link): ?Customer
    {
        $invitation = $this->invitationForLink($link);

        if (! $invitation?->guarantor_customer_id) {
            return null;
        }

        return Customer::find($invitation->guarantor_customer_id);
    }
}
