<?php

namespace App\Services;

use App\Models\PartnerPayment;
use App\Models\PartnerPayoutRequest;
use App\Models\Vendor;
use Illuminate\Support\Facades\Schema;

class PartnerPayoutRequestService
{
    public function availableBalance(Vendor $vendor, string $sourceType): float
    {
        $approved = (float) PartnerPayment::query()
            ->where('partner_id', $vendor->id)
            ->where('source_type', $sourceType)
            ->where('status', 'approved')
            ->sum('amount');

        $payoutQuery = PartnerPayoutRequest::query()
            ->where('partner_id', $vendor->id)
            ->whereIn('status', ['pending', 'approved']);

        $sourceColumn = Schema::hasColumn('partner_payout_requests', 'source_type')
            ? 'source_type'
            : (Schema::hasColumn('partner_payout_requests', 'wallet_type') ? 'wallet_type' : 'source_type');

        $reserved = (float) (clone $payoutQuery)
            ->where($sourceColumn, $sourceType)
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

        $sourceColumn = Schema::hasColumn('partner_payout_requests', 'source_type')
            ? 'source_type'
            : 'wallet_type';

        return PartnerPayoutRequest::create([
            'partner_id'   => $vendor->id,
            $sourceColumn  => $sourceType,
            'amount'       => $amount,
            'status'       => 'pending',
            'notes'        => filled($notes) ? trim($notes) : null,
        ]);
    }
}
