<?php

namespace App\Services;

use App\Models\LoanAgreement;
use Illuminate\Support\Collection;

class OfferLetterExpiryService
{
    /** @return Collection<int, LoanAgreement> */
    public function expireStaleOffers(): Collection
    {
        $offers = LoanAgreement::query()
            ->where('document_type', 'offer_letter')
            ->where('status', 'sent')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($offers as $offer) {
            $offer->update(['status' => 'expired']);
        }

        return $offers;
    }

    public function isSigningAllowed(LoanAgreement $agreement): bool
    {
        if ($agreement->document_type !== 'offer_letter') {
            return true;
        }

        $this->expireIfStale($agreement);

        return ! $agreement->fresh()->isOfferExpired();
    }

    public function expireIfStale(LoanAgreement $agreement): LoanAgreement
    {
        if ($agreement->document_type !== 'offer_letter') {
            return $agreement;
        }

        if ($agreement->status === 'sent'
            && $agreement->expires_at
            && now()->greaterThan($agreement->expires_at)) {
            $agreement->update(['status' => 'expired']);
            $agreement = $agreement->fresh();
        }

        return $agreement;
    }
}
