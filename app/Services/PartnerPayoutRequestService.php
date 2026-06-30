<?php

namespace App\Services;

use App\Models\PartnerPayoutRequest;
use App\Models\Vendor;

class PartnerPayoutRequestService
{
    public function request(Vendor $partner, string $walletType, float $amount, ?string $notes = null): PartnerPayoutRequest
    {
        $walletType = in_array($walletType, ['affiliate_commission', 'recovery_commission', 'vendor_task'], true)
            ? $walletType
            : 'affiliate_commission';

        $available = $this->availableBalance($partner, $walletType);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Enter a payout amount greater than zero.');
        }
        if ($amount > $available) {
            throw new \InvalidArgumentException('Requested amount exceeds your available balance.');
        }

        $pending = PartnerPayoutRequest::query()
            ->where('partner_id', $partner->id)
            ->where('wallet_type', $walletType)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            throw new \InvalidArgumentException('You already have a pending payout request for this wallet.');
        }

        return PartnerPayoutRequest::create([
            'partner_id'  => $partner->id,
            'wallet_type' => $walletType,
            'amount'      => round($amount, 2),
            'status'      => 'pending',
            'notes'       => $notes,
        ]);
    }

    public function availableBalance(Vendor $partner, string $walletType): float
    {
        return match ($walletType) {
            'recovery_commission' => (float) (app(RecoveryCommissionWalletService::class)->summary($partner)['approved'] ?? 0),
            'affiliate_commission' => (float) (app(AffiliateCommissionWalletService::class)->summary($partner)['approved'] ?? 0),
            default => 0.0,
        };
    }
}
