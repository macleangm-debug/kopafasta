<?php

namespace App\Services;

use App\Models\Setting;

class OfferSettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::get("offer.$key", $default);
    }

    public function requireOfferAcceptanceCode(): bool
    {
        return (bool) $this->get('require_offer_acceptance_code', false);
    }

    public function requireContractAcceptanceCode(): bool
    {
        return (bool) $this->get('require_contract_acceptance_code', false);
    }

    public function repaymentCommencementDays(): int
    {
        return max(0, (int) $this->get('repayment_commencement_days', 7));
    }
}
