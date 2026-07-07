<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyPointTransaction;

class UnifiedRewardsService
{
    public function __construct(
        private readonly LoyaltyPointsService $loyalty,
        private readonly ReferralService $referrals,
    ) {}

    /** @return array{loyalty: int, referral: int, total: int, wallet_tzs: float} */
    public function wallet(Customer $customer): array
    {
        $loyalty = $this->loyalty->balance($customer);
        $walletTzs = (float) $this->referrals->wallet($customer)->balance;
        $referral = wallet_balance_as_points($walletTzs);

        return [
            'loyalty'    => $loyalty,
            'referral'   => $referral,
            'total'      => $loyalty + $referral,
            'wallet_tzs' => $walletTzs,
        ];
    }

    public function totalPoints(Customer $customer): int
    {
        return $this->wallet($customer)['total'];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, LoyaltyPointTransaction> */
    public function recentActivity(Customer $customer, int $limit = 15)
    {
        return $this->loyalty->recentTransactions($customer, $limit);
    }
}
