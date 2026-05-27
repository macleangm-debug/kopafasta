<?php

namespace App\Observers;

use App\Models\CustomerKyc;
use App\Services\MembershipService;

/**
 * Auto-issue membership the first time a customer's KYC is approved.
 * Idempotent: only fires when status transitions to "approved" AND the
 * customer doesn't already have a membership.
 */
class CustomerKycObserver
{
    public function __construct(private MembershipService $membership)
    {
    }

    public function saved(CustomerKyc $kyc): void
    {
        if ($kyc->status !== 'approved') {
            return;
        }

        // Only act on the transition (or first time we see "approved").
        if (! $kyc->wasChanged('status') && ! $kyc->wasRecentlyCreated) {
            return;
        }

        $customer = $kyc->customer;
        if (! $customer) {
            return;
        }
        if ($customer->hasMembership()) {
            return; // already issued — keep existing expiry
        }

        $this->membership->issue(
            customer: $customer,
            actorUserId: $kyc->verified_by,
        );
    }
}
