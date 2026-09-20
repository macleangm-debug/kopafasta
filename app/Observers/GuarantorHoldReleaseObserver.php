<?php

namespace App\Observers;

use App\Models\Customer;
use App\Services\GuarantorInvitationService;
use App\Services\ProfileCompletionService;

/**
 * Profile completion used to release a guarantor hold only from one borrower
 * save action. A later save from any other path left an already-complete
 * guarantor on awaiting_guarantor. Re-evaluate the canonical release whenever
 * the member record is saved and the profile is complete.
 */
class GuarantorHoldReleaseObserver
{
    private static bool $releasing = false;

    public function saved(Customer $customer): void
    {
        self::evaluate($customer);
    }

    public static function evaluate(?Customer $customer): void
    {
        if (! $customer || self::$releasing || config('kopafasta.suppress_guarantor_release')) {
            return;
        }

        if (! app(ProfileCompletionService::class)->isFullyComplete($customer)) {
            return;
        }

        self::$releasing = true;
        try {
            app(GuarantorInvitationService::class)->releaseHeldApplicationsForGuarantor($customer);
        } finally {
            self::$releasing = false;
        }
    }
}
