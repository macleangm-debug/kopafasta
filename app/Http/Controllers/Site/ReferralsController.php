<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralsController extends Controller
{
    public function show(Request $request, ReferralService $referrals): View
    {
        $customer = $request->user()?->customer;
        abort_unless($customer, 403);

        $referrals->ensureCode($customer);

        return view('site.borrower.referrals', [
            'customer'         => $customer->fresh(),
            'referralCode'     => $customer->referral_code,
            'referralLink'     => $referrals->referralLink($customer),
            'referralWallet'   => $referrals->wallet($customer),
            'referralSettings' => $referrals->settings(),
            'walletRules'      => $referrals->walletRules(),
        ]);
    }
}
