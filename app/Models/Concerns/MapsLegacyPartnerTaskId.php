<?php

namespace App\Models\Concerns;

trait MapsLegacyPartnerTaskId
{
    public function getVendorTaskIdAttribute(): ?int
    {
        return isset($this->attributes['partner_task_id'])
            ? (int) $this->attributes['partner_task_id']
            : null;
    }

    public function setVendorTaskIdAttribute(mixed $value): void
    {
        $this->attributes['partner_task_id'] = $value;
    }
}
