<?php

use App\Models\Customer;
use App\Services\LoyaltyPointsService;
use App\Services\ReferralService;

/** TZS credited to referral wallet per 1 spendable referral point (default 1:1). */
function referral_points_per_tzs(): int
{
    return max(1, (int) config('gamification.referral_wallet.points_per_tzs', 1));
}

/** Convert referral wallet balance (TZS) to spendable referral points for display. */
function wallet_balance_as_points(float $walletBalance): int
{
    return (int) floor($walletBalance / referral_points_per_tzs());
}

/** Convert referral points to wallet debit amount (TZS). */
function referral_points_to_wallet_amount(int $points): float
{
    return round($points * referral_points_per_tzs(), 2);
}

function format_reward_points(int $points): string
{
    return number_format($points).' '.__('borrower.rewards.points_short');
}

/** @return array{loyalty: int, referral: int, total: int} */
function customer_reward_points(Customer $customer): array
{
    $loyalty = app(LoyaltyPointsService::class)->balance($customer);
    $referral = wallet_balance_as_points((float) app(ReferralService::class)->wallet($customer)->balance);

    return [
        'loyalty'  => $loyalty,
        'referral' => $referral,
        'total'    => $loyalty + $referral,
    ];
}
