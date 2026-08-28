<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\LoyaltyPointsService;
use App\Services\LoyaltyRedemptionService;
use App\Services\MemberEngagementService;
use App\Services\ReferralLeaderboardService;
use App\Services\ReferralService;
use App\Services\RepaymentStreakRewardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EngagementHubController extends Controller
{
    public function show(
        Request $request,
        ReferralService $referrals,
        MemberEngagementService $engagement,
        ReferralLeaderboardService $leaderboard,
        LoyaltyPointsService $points,
        LoyaltyRedemptionService $redemptions,
        RepaymentStreakRewardService $streakRewards,
    ): View {
        $customer = $request->user()?->customer;
        abort_unless($customer, 403);

        $referrals->ensureCode($customer);
        $customer = $customer->fresh();
        $tab = in_array($request->query('tab'), ['referrals', 'rewards', 'streak'], true)
            ? $request->query('tab')
            : 'overview';

        return view('site.borrower.engagement', [
            'customer'              => $customer,
            'tab'                   => $tab,
            'engagement'            => $engagement->summary($customer),
            'referralCode'          => $customer->referral_code,
            'referralLink'          => $referrals->referralLink($customer),
            'referralShareMessage'  => $referrals->shareMessage($customer),
            'referralWallet'        => $referrals->wallet($customer),
            'referralSettings'      => $referrals->settings(),
            'progress'              => $engagement->referralProgress($customer),
            'level'                 => $engagement->referralLevel($customer),
            'rewardHistory'         => $referrals->rewardHistory($customer),
            'leaderboard'           => $leaderboard->topThisMonth(),
            'yourRank'              => $leaderboard->rankFor($customer),
            'rewardWallet'          => app(\App\Services\UnifiedRewardsService::class)->wallet($customer),
            'pointsBalance'         => app(\App\Services\UnifiedRewardsService::class)->totalPoints($customer),
            'catalog'               => $redemptions->catalog(null, $customer),
            'rewardsDashboard'      => $redemptions->dashboard($customer),
            'activeRewards'         => $redemptions->activeRewards($customer),
            'pointsHistory'         => $redemptions->history($customer),
            'transactions'          => $points->recentTransactions($customer, 15),
            'streakReward'          => $streakRewards->status($customer),
        ]);
    }

    public function redeem(Request $request, LoyaltyRedemptionService $redemptions): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_unless($customer, 403);

        $data = $request->validate([
            'option_key' => ['required', 'string', 'max:60'],
        ]);

        try {
            $reward = $redemptions->redeem($customer, $data['option_key']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        \App\Support\Celebration::flashOne('reward_redeemed');

        $appliesAtCheckout = $reward->benefit_type === 'rate_discount'
            || ($reward->benefit_type === 'percent_discount' && $reward->fee_type === 'application_fee');

        if ($appliesAtCheckout) {
            return redirect()->route('site.borrower.loan-products')
                ->with('status', __('borrower.rewards.redeemed'));
        }

        return redirect()->route('site.borrower.engagement', ['tab' => 'rewards'])
            ->with('status', __('borrower.rewards.redeemed'));
    }
}
