<?php

namespace App\Observers;

use App\Models\CustomerDocument;

/**
 * A required document can complete a guarantor after the profile row itself
 * was last saved. Re-evaluate the hold from that document, not from one screen.
 */
class GuarantorDocumentHoldReleaseObserver
{
    public function saved(CustomerDocument $document): void
    {
        GuarantorHoldReleaseObserver::evaluate($document->customer);
    }
}
