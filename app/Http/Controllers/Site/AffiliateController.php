<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PartnerPayment;
use App\Models\Vendor;
use App\Services\AffiliateCommissionWalletService;
use App\Services\AffiliateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    protected function affiliate(): Vendor
    {
        $vendor = Vendor::where('user_id', Auth::id())->first();
        abort_unless($vendor && $vendor->isAffiliate(), 403, 'Affiliate portal access requires an active affiliate account.');
        abort_unless(app(\App\Services\AffiliateLifecycleService::class)->canAccessPortal($vendor), 403, 'This affiliate account has been terminated.');

        return $vendor;
    }

    public function dashboard(AffiliateService $affiliates): View
    {
        $vendor = $this->affiliate();
        $affiliates->ensureCode($vendor);
        $vendor->refresh();

        $wallet = app(AffiliateCommissionWalletService::class)->summary($vendor);
        $lifecycle = app(\App\Services\AffiliateLifecycleService::class);

        return view('site.affiliate.dashboard', [
            'vendor'         => $vendor,
            'stats'          => $affiliates->stats($vendor),
            'wallet'         => $wallet,
            'shareMessage'   => $affiliates->renderMessage($vendor, 'share_template'),
            'links'          => $affiliates->messageContext($vendor),
            'commissionMode' => app(\App\Services\AffiliateSettingsService::class)->commissionMode(),
            'lifecycleStatus'=> $lifecycle->statusFor($vendor),
            'lifecycleLabel' => $lifecycle->label($lifecycle->statusFor($vendor)),
            'evaluation'     => $vendor->affiliate_evaluation_snapshot,
            'leaderboardRank'=> $vendor->affiliate_leaderboard_rank,
            'canShare'       => $lifecycle->canSharePublicly($vendor),
        ]);
    }

    public function referrals(AffiliateService $affiliates): View
    {
        $vendor = $this->affiliate();

        return view('site.affiliate.referrals', [
            'vendor'      => $vendor,
            'breakdown'   => $affiliates->attributionBreakdown($vendor),
            'recent'      => $affiliates->recentEvents($vendor),
        ]);
    }

    public function wallet(Request $request): View
    {
        $vendor = $this->affiliate();
        $walletService = app(AffiliateCommissionWalletService::class);

        return view('site.affiliate.wallet', [
            'vendor'   => $vendor,
            'summary'  => $walletService->summary($vendor),
            'payments' => $walletService->paginated($vendor, 15),
        ]);
    }

    public function disputePayment(Request $request, PartnerPayment $payment): RedirectResponse
    {
        $vendor = $this->affiliate();

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            app(AffiliateCommissionWalletService::class)->dispute($payment, $vendor, $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', 'Commission dispute submitted for review.');
    }

    public function requestPayout(Request $request): RedirectResponse
    {
        $vendor = $this->affiliate();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(\App\Services\PartnerPayoutRequestService::class)->request(
                $vendor,
                'affiliate_commission',
                (float) $data['amount'],
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Payout request submitted for admin approval.');
    }

    public function profile(): RedirectResponse
    {
        $this->affiliate();

        return redirect()->route('site.partner.profile');
    }
}
