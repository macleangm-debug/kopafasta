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
        $canChangeCode = app(AffiliateService::class)->canChangeCode($vendor);

        return view('site.affiliate.profile', compact('vendor', 'links', 'share', 'canChangeCode'));
    }

    public function documents(): View
    {
        $vendor = $this->affiliate();

        return view('site.affiliate.documents', compact('vendor'));
    }

    public function settings(): View
    {
        $vendor = $this->affiliate();
        $membership = app(\App\Services\AffiliateMembershipService::class)->summary($vendor);

        return view('site.affiliate.settings', compact('vendor', 'membership'));
    }

    public function membershipPayForm(Request $request): View|RedirectResponse
    {
        $vendor = $this->affiliate();
        $service = app(\App\Services\AffiliateMembershipService::class);
        $cfg = \App\Services\AffiliateMembershipService::config();

        if (! $cfg['enabled']) {
            return redirect()->route('site.affiliate.settings');
        }

        if ($service->isActive($vendor) && ! ($vendor->membership_expires_at?->lte(now()->addDays(30)))) {
            return redirect()->route('site.affiliate.settings')
                ->with('status', __('site.affiliate_portal.membership_active'));
        }

        $vendor = $service->startPaymentWindow($vendor);
        $paymentReference = $vendor->membership_payment_reference ?: $service->generatePaymentReference($vendor);
        $request->session()->put('affiliate_membership_payment_ref', $paymentReference);

        $accounts = app(\App\Services\PaymentAccountService::class);
        $bankAccounts = $accounts->bankAccountsForDisplay('registration_fee', $paymentReference);
        $mobileResolved = $accounts->resolve('registration_fee', 'mobile_money');
        $mobileDetails = $accounts->mobileMoneyDetails($mobileResolved['mobile_money_account'] ?? null, $paymentReference);

        return view('site.affiliate.membership-pay', [
            'vendor' => $vendor,
            'config' => $cfg,
            'paymentReference' => $paymentReference,
            'bankAccounts' => $bankAccounts,
            'mobileDetails' => $mobileDetails,
        ]);
    }

    public function membershipPay(Request $request): RedirectResponse
    {
        $vendor = $this->affiliate();
        $service = app(\App\Services\AffiliateMembershipService::class);

        $data = $request->validate([
            'channel' => ['required', 'in:mobile_money,bank'],
            'payment_phone' => ['nullable', 'string', 'max:30'],
            'payment_reference' => ['nullable', 'string', 'max:64'],
        ]);

        $ref = $data['payment_reference']
            ?: $request->session()->pull('affiliate_membership_payment_ref')
            ?: $service->generatePaymentReference($vendor);

        if ($data['channel'] === 'bank') {
            $vendor->update([
                'membership_status' => 'pending_payment',
                'membership_payment_reference' => $ref,
            ]);

            return redirect()->route('site.affiliate.settings')
                ->with('status', __('site.affiliate_portal.membership_pending').' · '.$ref);
        }

        $service->activate($vendor, $ref);

        return redirect()->route('site.affiliate.settings')
            ->with('status', __('site.affiliate_portal.membership_paid'));
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
            'payout_type'    => ['nullable', 'in:mobile_money,bank'],
            'payout_account_name' => ['nullable', 'string', 'max:120'],
            'payout_mobile_provider' => ['nullable', 'string', 'max:40'],
            'payout_mobile_number' => ['nullable', 'string', 'max:30'],
            'payout_bank_name' => ['nullable', 'string', 'max:120'],
            'payout_account_number' => ['nullable', 'string', 'max:60'],
            'residence_region' => ['nullable', 'string', 'max:80'],
            'residence_district' => ['nullable', 'string', 'max:80'],
            'residence_street' => ['nullable', 'string', 'max:160'],
            'activity_type' => ['nullable', 'string', 'max:80'],
            'activity_details' => ['nullable', 'string', 'max:2000'],
        ]);

        if (filled($data['affiliate_code'] ?? null)) {
            try {
                app(AffiliateService::class)->updateCode($vendor, $data['affiliate_code']);
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['affiliate_code' => $e->getMessage()])->withInput();
            }
        }

        $meta = $vendor->metadata ?? [];
        if (filled($data['payout_type'] ?? null)) {
            $meta['payout_account'] = array_filter([
                'type' => $data['payout_type'],
                'account_name' => $data['payout_account_name'] ?? null,
                'mobile_provider' => $data['payout_mobile_provider'] ?? null,
                'mobile_number' => $data['payout_mobile_number'] ?? null,
                'bank_name' => $data['payout_bank_name'] ?? null,
                'account_number' => $data['payout_account_number'] ?? null,
            ]);
        }

        if ($request->hasAny(['residence_region', 'residence_district', 'residence_street'])) {
            $meta['residence'] = array_filter([
                'region' => $data['residence_region'] ?? null,
                'district' => $data['residence_district'] ?? null,
                'street' => $data['residence_street'] ?? null,
            ]);
        }

        if ($request->hasAny(['activity_type', 'activity_details'])) {
            $meta['activity'] = array_filter([
                'type' => $data['activity_type'] ?? null,
                'details' => $data['activity_details'] ?? null,
            ]);
        }

        unset(
            $data['affiliate_code'],
            $data['payout_type'],
            $data['payout_account_name'],
            $data['payout_mobile_provider'],
            $data['payout_mobile_number'],
            $data['payout_bank_name'],
            $data['payout_account_number'],
            $data['residence_region'],
            $data['residence_district'],
            $data['residence_street'],
            $data['activity_type'],
            $data['activity_details'],
        );

        $data['metadata'] = $meta;
        $vendor->update($data);

        return back()->with('status', __('site.affiliate_portal.profile_saved'));
    }

    public function updateDocuments(Request $request): RedirectResponse
    {
        $vendor = $this->affiliate();

        $request->validate([
            'affiliate_id'      => ['nullable', 'image', 'max:5120'],
            'face_front'        => ['nullable', 'image', 'max:5120'],
            'face_left'         => ['nullable', 'image', 'max:5120'],
            'face_right'        => ['nullable', 'image', 'max:5120'],
            'face_holding_id'   => ['nullable', 'image', 'max:5120'],
        ]);

        $data = [];
        $meta = $vendor->metadata ?? [];
        $faces = is_array($meta['face_captures'] ?? null) ? $meta['face_captures'] : [];

        foreach ([
            'face_front' => 'front',
            'face_left' => 'left',
            'face_right' => 'right',
            'face_holding_id' => 'holding_id',
        ] as $field => $key) {
            if ($request->hasFile($field)) {
                $faces[$key] = $request->file($field)->store("partners/{$vendor->id}/kyc", 'public');
            }
        }

        if ($faces !== ($meta['face_captures'] ?? [])) {
            $meta['face_captures'] = $faces;
            $data['metadata'] = $meta;
            // Keep legacy selfie path as the front-facing capture for admin review screens.
            if (filled($faces['front'] ?? null)) {
                $data['affiliate_selfie_path'] = $faces['front'];
            }
        }

        if ($request->hasFile('affiliate_id')) {
            $data['affiliate_id_path'] = $request->file('affiliate_id')->store("partners/{$vendor->id}/kyc", 'public');
        }

        $selfie = $data['affiliate_selfie_path'] ?? $faces['front'] ?? $vendor->affiliate_selfie_path;
        $idDoc = $data['affiliate_id_path'] ?? $vendor->affiliate_id_path;
        $facesReady = filled($faces['front'] ?? null)
            && filled($faces['left'] ?? null)
            && filled($faces['right'] ?? null)
            && filled($faces['holding_id'] ?? null);

        if (($selfie || $facesReady) && $idDoc) {
            $data['affiliate_kyc_status'] = 'submitted';
        }

        if ($data !== []) {
            $vendor->update($data);
        }

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
