<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\LoyaltyPointsService;
use App\Services\LoyaltyRedemptionService;
use App\Services\MemberEngagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RewardsController extends Controller
{
    public function show(
        Request $request,
        LoyaltyPointsService $points,
        LoyaltyRedemptionService $redemptions,
        MemberEngagementService $engagement,
    ): View {
        $customer = $request->user()?->customer;
        abort_unless($customer, 403);

        return view('site.borrower.rewards', [
            'customer'       => $customer,
            'balance'        => $points->balance($customer),
            'catalog'        => $redemptions->catalog(),
            'activeRewards'  => $redemptions->activeRewards($customer),
            'history'        => $redemptions->history($customer),
            'transactions'   => $points->recentTransactions($customer, 15),
            'engagement'     => $engagement->summary($customer),
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
            $redemptions->redeem($customer, $data['option_key']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('borrower.rewards.redeemed'));
    }
}
