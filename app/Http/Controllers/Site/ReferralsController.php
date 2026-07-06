<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\MemberEngagementService;
use App\Services\ReferralLeaderboardService;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralsController extends Controller
{
    public function show(
        Request $request,
        ReferralService $referrals,
        MemberEngagementService $engagement,
        ReferralLeaderboardService $leaderboard,
    ): View {
        $customer = $request->user()?->customer;
        abort_unless($customer, 403);

        $referrals->ensureCode($customer);
        $customer = $customer->fresh();
        $progress = $engagement->referralProgress($customer);
        $level = $engagement->referralLevel($customer);
        $rewardHistory = $referrals->rewardHistory($customer);
        $leaderboardRows = $leaderboard->topThisMonth();
        $yourRank = $leaderboard->rankFor($customer);

        return view('site.borrower.referrals', [
            'customer'             => $customer,
            'referralCode'         => $customer->referral_code,
            'referralLink'         => $referrals->referralLink($customer),
            'referralShareMessage' => $referrals->shareMessage($customer),
            'referralShareMessageSw'=> $referrals->shareMessage($customer, 'sw'),
            'referralWallet'       => $referrals->wallet($customer),
            'referralSettings'     => $referrals->settings(),
            'walletRules'          => $referrals->walletRules(),
            'progress'             => $progress,
            'level'                => $level,
            'rewardHistory'        => $rewardHistory,
            'leaderboard'          => $leaderboardRows,
            'yourRank'             => $yourRank,
            'engagement'           => $engagement->summary($customer),
        ]);
    }
}
