<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AffiliateEvent;
use App\Models\PartnerPayment;
use App\Models\Vendor;
use App\Services\AffiliatePortalPresenter;
use App\Services\AffiliateService;
use App\Services\AffiliateSettingsService;
use App\Services\PartnerPayoutRequestService;
use App\Services\PartnerProfileService;
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
        $data = app(AffiliatePortalPresenter::class)->dashboard($this->affiliate());

        return view('site.affiliate.dashboard', $data);
    }

    public function share(): View
    {
        return view('site.affiliate.share', app(AffiliatePortalPresenter::class)->share($this->affiliate()));
    }

    public function performance(): View
    {
        return view('site.affiliate.performance', app(AffiliatePortalPresenter::class)->performance($this->affiliate()));
    }

    public function referrals(): View
    {
        return view('site.affiliate.referrals', app(AffiliatePortalPresenter::class)->referrals($this->affiliate()));
    }

    public function wallet(): View
    {
        return view('site.affiliate.wallet', app(AffiliatePortalPresenter::class)->wallet($this->affiliate()));
    }

    public function agreement(): View
    {
        $vendor = $this->affiliate();
        $accountTabs = [
            ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.affiliate.profile')],
            ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.affiliate.settings')],
        ];

        return view('site.affiliate.agreement', app(AffiliatePortalPresenter::class)->agreementDocument($vendor) + [
            'partner' => $vendor,
            'portal' => 'affiliate',
            'profileRoute' => 'site.affiliate.profile',
            'accountTabs' => $accountTabs,
            'eyebrow' => __('site.affiliate_portal.title'),
            'title' => __('site.affiliate_portal.agreement_title'),
        ]);
    }

    public function profile(Request $request, ?string $section = null): View|RedirectResponse
    {
        $vendor = $this->affiliate();
        app(AffiliateService::class)->ensureCode($vendor);

        $section = $section ?: 'hub';

        if (! in_array($section, array_merge(['hub', 'agreement', 'membership'], PartnerProfileService::SECTIONS), true)) {
            return redirect()->route('site.affiliate.profile');
        }

        $accountTabs = [
            ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.affiliate.profile')],
            ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.affiliate.settings')],
        ];

        $common = [
            'partner'         => $vendor,
            'portal'          => 'affiliate',
            'profileRoute'    => 'site.affiliate.profile',
            'updateRoute'     => 'site.affiliate.profile.update',
            'layoutComponent' => 'site.affiliate-layout',
            'eyebrow'         => __('site.affiliate_portal.title'),
            'accountTabs'     => $accountTabs,
            'commercial'      => app(\App\Services\AffiliateMembershipService::class)->summary($vendor),
        ];

        if ($section === 'hub') {
            return view('site.partner-account.hub', $common + [
                'title'    => __('site.partner_account.hub_title'),
                'subtitle' => __('site.partner_account.hub_subtitle'),
            ]);
        }

        if ($section === 'agreement') {
            return view('site.affiliate.agreement', app(AffiliatePortalPresenter::class)->agreementDocument($vendor) + $common + [
                'title' => __('site.affiliate_portal.agreement_title'),
            ]);
        }

        if ($section === 'membership') {
            if ($vendor->isPremiumAffiliate() && ! app(AffiliateSettingsService::class)->premiumMembershipRequired()) {
                return redirect()->route('site.affiliate.agreement');
            }

            return view('site.affiliate.membership', $common + [
                'title' => __('site.affiliate_portal.membership_title'),
                'membership' => $common['commercial'],
            ]);
        }

        return view('site.partner-account.'.$section, $common + [
            'title'         => __('site.partner_account.'.$section.'_section'),
            'canChangeCode' => app(AffiliateService::class)->canChangeCode($vendor),
            'nextCodeChangeAt' => app(AffiliateService::class)->nextCodeChangeAt($vendor),
        ]);
    }

    public function documents(): RedirectResponse
    {
        return redirect()->route('site.affiliate.profile', ['section' => 'face']);
    }

    public function settings(): View
    {
        return view('site.affiliate.settings', [
            'vendor' => $this->affiliate(),
        ]);
    }

    public function terms(Request $request): View|RedirectResponse
    {
        $vendor = $this->affiliate();
        $terms = app(\App\Services\AffiliateTermsService::class);
        $document = app(AffiliatePortalPresenter::class)->agreementDocument($vendor);

        return view('site.affiliate.terms', $document + [
            'vendor' => $vendor,
            'rendered' => $terms->render($vendor),
            'accepted' => $terms->latestAcceptance($vendor),
        ]);
    }

    public function acceptTerms(Request $request): RedirectResponse
    {
        $vendor = $this->affiliate();
        $terms = app(\App\Services\AffiliateTermsService::class);

        $request->validate([
            'affiliate_terms_accepted' => ['accepted'],
        ]);

        if (! $terms->hasAccepted($vendor)) {
            $terms->accept($vendor, $request);
            $vendor = $vendor->fresh();
        }

        if ($vendor->isPremiumAffiliate()) {
            return redirect()
                ->route('site.affiliate.dashboard')
                ->with('status', __('site.affiliate_portal.agreement_active'));
        }

        return redirect()
            ->route('site.affiliate.membership.pay')
            ->with('status', __('affiliate_terms.already_accepted'));
    }

    public function membershipPayForm(Request $request): View|RedirectResponse
    {
        $vendor = $this->affiliate();
        $service = app(\App\Services\AffiliateMembershipService::class);
        $cfg = \App\Services\AffiliateMembershipService::config();

        if ($vendor->isPremiumAffiliate() && ! app(AffiliateSettingsService::class)->premiumMembershipRequired()) {
            return redirect()->route('site.affiliate.agreement');
        }

        if (! $cfg['enabled']) {
            return redirect()->route('site.affiliate.settings');
        }

        if (($cfg['require_terms_before_activation'] ?? true)
            && ! app(\App\Services\AffiliateTermsService::class)->hasAccepted($vendor)
            && ! $service->isActive($vendor)) {
            return redirect()->route('site.affiliate.terms')
                ->with('error', __('affiliate_terms.required_before_membership'));
        }

        if ($service->isActive($vendor) && ! $service->withinRenewalWindow($vendor)) {
            return redirect()->route('site.affiliate.profile', ['section' => 'membership'])
                ->with('status', __('site.affiliate_portal.membership_active'));
        }

        $payment = app(\App\Services\PartnerMembershipPaymentService::class)->open($vendor);
        $accounts = app(\App\Services\PaymentAccountService::class);
        $bankAccounts = $accounts->bankAccountsForDisplay('partner_membership', $payment->reference);
        $canSwitchToBank = (bool) $accounts->resolveBankAccount('partner_membership');

        return view('site.affiliate.membership-pay', [
            'vendor' => $vendor,
            'payment' => $payment,
            'config' => $cfg,
            'bankAccounts' => $bankAccounts,
            'canSwitchToBank' => $canSwitchToBank,
            'payUrl' => route('site.affiliate.membership.checkout.pay', $payment),
            'statusUrl' => route('site.affiliate.membership.checkout.status', $payment),
            'retryUrl' => route('site.affiliate.membership.checkout.retry', $payment),
            'gateUrl' => route('site.affiliate.membership.checkout.gate', $payment),
            'successUrl' => route('site.affiliate.dashboard'),
        ]);
    }

    public function membershipPay(Request $request): RedirectResponse
    {
        return redirect()->route('site.affiliate.membership.pay');
    }

    public function updateProfile(Request $request, string $section = 'personal'): RedirectResponse
    {
        $vendor = $this->affiliate();

        if (! in_array($section, PartnerProfileService::SECTIONS, true)) {
            abort(404);
        }

        try {
            app(PartnerProfileService::class)->updateSection($vendor, $section, $request);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['affiliate_code' => $e->getMessage()])->withInput();
        }

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
            app(\App\Services\AffiliateCommissionWalletService::class)->dispute($payment, $vendor, $request->input('reason'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('site.affiliate_portal.dispute_submitted'));
    }

    public function notifications(): View
    {
        $vendor = $this->affiliate();
        $notifications = \App\Models\NotificationLog::query()
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('notification_logs', 'user_id'),
                fn ($q) => $q->where('user_id', Auth::id()),
                fn ($q) => $q->where(function ($inner) {
                    $inner->where('recipient', Auth::user()?->email)
                        ->orWhere('recipient', Auth::user()?->phone);
                })
            )
            ->latest()
            ->paginate(20);

        return view('site.affiliate.notifications', compact('vendor', 'notifications'));
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
