<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AffiliateEvent;
use App\Models\PartnerPayment;
use App\Models\Vendor;
use App\Services\AffiliateCommissionWalletService;
use App\Services\AffiliateService;
use App\Services\AffiliateSettingsService;
use App\Services\PartnerPayoutRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    protected function affiliate(): Vendor
    {
        $vendor = Vendor::where('user_id', Auth::id())->first();
        abort_unless($vendor && $vendor->isAffiliate(), 403);

        return $vendor;
    }

    public function dashboard(Request $request): View
    {
        $vendor = $this->affiliate();
        app(AffiliateService::class)->ensureCode($vendor);

        $affiliateService = app(AffiliateService::class);
        $stats = $affiliateService->stats($vendor);
        $links = $affiliateService->messageContext($vendor);
        $share = $affiliateService->renderMessage($vendor, 'share_template');
        $wallet = app(AffiliateCommissionWalletService::class)->summary($vendor);
        $minPayout = app(AffiliateSettingsService::class)->minimumPayoutAmount();

        return view('site.affiliate.dashboard', compact('vendor', 'stats', 'links', 'share', 'wallet', 'minPayout'));
    }

    public function referrals(): View
    {
        $vendor = $this->affiliate();

        $events = AffiliateEvent::query()
            ->where('partner_id', $vendor->id)
            ->with(['customer', 'loanApplication'])
            ->latest()
            ->paginate(20);

        return view('site.affiliate.referrals', compact('vendor', 'events'));
    }

    public function wallet(): View
    {
        $vendor = $this->affiliate();
        $walletService = app(AffiliateCommissionWalletService::class);
        $summary = $walletService->summary($vendor);
        $payments = $walletService->paginated($vendor);
        $minPayout = app(AffiliateSettingsService::class)->minimumPayoutAmount();
        $available = app(PartnerPayoutRequestService::class)->availableBalance($vendor, 'affiliate_commission');

        return view('site.affiliate.wallet', compact('vendor', 'summary', 'payments', 'minPayout', 'available'));
    }

    public function profile(): View
    {
        $vendor = $this->affiliate();
        app(AffiliateService::class)->ensureCode($vendor);
        $links = app(AffiliateService::class)->messageContext($vendor);
        $share = app(AffiliateService::class)->renderMessage($vendor, 'share_template');

        return view('site.affiliate.profile', compact('vendor', 'links', 'share'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $vendor = $this->affiliate();

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'email'          => ['nullable', 'email', 'max:120'],
            'address'        => ['nullable', 'string', 'max:255'],
            'affiliate_code' => ['nullable', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_-]+$/'],
            'affiliate_selfie' => ['nullable', 'image', 'max:5120'],
            'affiliate_id'     => ['nullable', 'image', 'max:5120'],
            'affiliate_photo'  => ['nullable', 'image', 'max:5120'],
        ]);

        if (filled($data['affiliate_code'] ?? null)) {
            try {
                app(AffiliateService::class)->updateCode($vendor, $data['affiliate_code']);
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['affiliate_code' => $e->getMessage()])->withInput();
            }
        }
        unset($data['affiliate_code'], $data['affiliate_selfie'], $data['affiliate_id'], $data['affiliate_photo']);

        if ($request->hasFile('affiliate_selfie')) {
            $data['affiliate_selfie_path'] = $request->file('affiliate_selfie')->store("partners/{$vendor->id}/kyc", 'public');
        }
        if ($request->hasFile('affiliate_id')) {
            $data['affiliate_id_path'] = $request->file('affiliate_id')->store("partners/{$vendor->id}/kyc", 'public');
        }
        if ($request->hasFile('affiliate_photo')) {
            $data['affiliate_photo_path'] = $request->file('affiliate_photo')->store("partners/{$vendor->id}/kyc", 'public');
        }
        if (! empty($data['affiliate_selfie_path']) && ! empty($data['affiliate_id_path'])) {
            $data['affiliate_kyc_status'] = 'submitted';
        }

        $vendor->update($data);

        return back()->with('status', __('site.affiliate_portal.profile_saved'));
    }

    public function disputePayment(Request $request, PartnerPayment $payment): RedirectResponse
    {
        $vendor = $this->affiliate();
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            app(AffiliateCommissionWalletService::class)->dispute($payment, $vendor, $request->input('reason'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('site.affiliate_portal.dispute_submitted'));
    }

    public function requestPayout(Request $request): RedirectResponse
    {
        $vendor = $this->affiliate();
        $minPayout = app(AffiliateSettingsService::class)->minimumPayoutAmount();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.$minPayout],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        try {
            app(PartnerPayoutRequestService::class)->request(
                $vendor,
                'affiliate_commission',
                (float) $data['amount'],
                $data['notes'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('site.affiliate_portal.payout_requested'));
    }
}
