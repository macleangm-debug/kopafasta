<?php

namespace App\Models\Concerns;

trait MapsLegacyPartnerId
{
    public function getVendorIdAttribute(): ?int
    {
        return isset($this->attributes['partner_id'])
            ? (int) $this->attributes['partner_id']
            : null;
    }

    public function setVendorIdAttribute(mixed $value): void
    {
        $this->attributes['partner_id'] = $value;
    }
}
