<?php

namespace App\Models\Concerns;

trait MapsLegacyAffiliatePartnerId
{
    public function getAffiliateVendorIdAttribute(): ?int
    {
        return isset($this->attributes['affiliate_partner_id'])
            ? (int) $this->attributes['affiliate_partner_id']
            : null;
    }

    public function setAffiliateVendorIdAttribute(mixed $value): void
    {
        $this->attributes['affiliate_partner_id'] = $value;
    }
}
