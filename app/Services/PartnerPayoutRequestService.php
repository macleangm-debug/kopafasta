<?php

namespace App\Services;

use App\Models\PartnerPayment;
use App\Models\PartnerPayoutRequest;
use App\Models\Vendor;

class PartnerPayoutRequestService
{
    public function availableBalance(Vendor $vendor, string $sourceType): float
    {
        $approved = (float) PartnerPayment::query()
            ->where('partner_id', $vendor->id)
            ->where('source_type', $sourceType)
            ->where('status', 'approved')
            ->sum('amount');

        $reserved = (float) PartnerPayoutRequest::query()
            ->where('partner_id', $vendor->id)
            ->where('source_type', $sourceType)
            ->whereIn('status', ['pending', 'approved'])
            ->sum('amount');

        return max(0, round($approved - $reserved, 2));
    }

    public function request(Vendor $vendor, string $sourceType, float $amount, ?string $notes = null): PartnerPayoutRequest
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payout amount must be greater than zero.');
        }

        if ($sourceType === 'affiliate_commission') {
            $min = app(AffiliateSettingsService::class)->minimumPayoutAmount();
            if ($amount < $min) {
                throw new \InvalidArgumentException(__('site.affiliate_portal.payout_minimum', ['amount' => format_money($min)]));
            }
        }

        $available = $this->availableBalance($vendor, $sourceType);
        if ($amount > $available) {
            throw new \InvalidArgumentException(__('site.affiliate_portal.payout_exceeds_balance', ['available' => format_money($available)]));
        }

        return PartnerPayoutRequest::create([
            'partner_id'  => $vendor->id,
            'source_type' => $sourceType,
            'amount'      => $amount,
            'status'      => 'pending',
            'notes'       => filled($notes) ? trim($notes) : null,
        ]);
    }
}
