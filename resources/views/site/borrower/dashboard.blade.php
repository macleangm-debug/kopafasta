<x-site.borrower-layout :title="brand_title('Dashboard')" active="dashboard" content-width="wide">

    @php
        $hero = $dashboardHero ?? [];
        $fullName = trim((string) ($customer->full_name ?? Auth::user()->name ?? ''));
        $hero['greeting'] = $fullName !== '' ? $fullName : (__('borrower.welcome').', '.(explode(' ', (string) (Auth::user()->name ?? ''))[0] ?? ''));
        $hero['membership_no'] = $customer->member_no ?? null;
        if (! empty($eligibility) && ($eligibility['has_data'] ?? false)) {
            $hero['eligibility_amount'] = format_money($eligibility['amount'] ?? 0);
            $hero['eligibility_hint'] = __('borrower.dashboard.eligibility_growth_hint_short');
        } elseif (! empty($eligibility) && ! ($eligibility['has_data'] ?? false)) {
            $hero['eligibility_hint'] = __('borrower.dashboard.eligibility_no_data_hint');
        }
        // Clean home card for active members; unpaid members still need clear guidance.
        if (in_array($hero['variant'] ?? '', ['applications', 'no_loan'], true)) {
            $needsMembership = ! ($customer->isMembershipActive() || $customer->isMembershipInGrace());
            if ($needsMembership && ($hero['variant'] ?? '') === 'no_loan') {
                $hero['title'] = __('borrower.membership.banner_title');
                $hero['subtitle'] = __('borrower.membership.banner_body');
                $hero['cta_label'] = __('borrower.membership.banner_cta');
                $hero['cta_url'] = route('site.membership.renew');
                $hero['secondary_cta_label'] = __('borrower.dashboard.hero.apply_now');
                $hero['secondary_cta_url'] = route('site.borrower.loan-products');
            } else {
                $hero['title'] = null;
                $hero['subtitle'] = null;
                $hero['secondary_cta_label'] = null;
                $hero['secondary_cta_url'] = null;
                if (($hero['variant'] ?? '') === 'applications') {
                    $hero['cta_label'] = __('borrower.dashboard.hero.view_application');
                }
            }
        }
    @endphp

    <x-site.borrower-dashboard-hero :hero="$hero" />

    @if (! empty($financialHealth))
        <x-site.borrower-financial-health :health="$financialHealth" />
    @endif

    <x-site.borrower-financial-snapshot :snapshot="$financialSnapshot ?? []" />

    <x-site.borrower-dashboard-quick-actions :active-loan="$activeLoan ?? null" />

    @if (! empty($groupInviteBanner['show']))
        <div class="mb-6 rounded-2xl border border-amber-300 bg-gradient-to-r from-amber-50 to-white p-5 sm:p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-amber-700 font-semibold mb-1">{{ __('borrower.apply.group.onboarding_label') }}</p>
                    <h2 class="text-lg font-bold text-gray-900">{{ $groupInviteBanner['title'] }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ $groupInviteBanner['message'] }}</p>
                </div>
                <a href="{{ $groupInviteBanner['cta_url'] }}"
                   class="inline-flex items-center justify-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-xl text-sm shrink-0">
                    {{ $groupInviteBanner['cta_label'] }}
                </a>
            </div>
        </div>
    @endif

    @if (! empty($kycSectionsDue))
        <div class="mb-6 rounded-xl bg-orange-50 ring-1 ring-orange-200 px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-orange-900">{{ __('borrower.dashboard.kyc_reconfirm_title') }}</p>
                <p class="text-xs text-orange-800 mt-1">{{ __('borrower.dashboard.kyc_reconfirm_body') }}</p>
            </div>
            <a href="{{ route('site.borrower.kyc-reconfirm') }}"
               class="inline-flex justify-center bg-orange-600 hover:bg-orange-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm shrink-0">
                {{ __('borrower.dashboard.kyc_reconfirm_cta') }}
            </a>
        </div>
    @endif

    @if ($referralCode ?? null)
        <section class="mb-8 bg-brand text-white rounded-2xl p-6 sm:p-8 shadow-lg relative overflow-hidden">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
            <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.referrals.grow') }}</p>
                    <h2 class="text-xl sm:text-2xl font-bold mt-1">{{ __('borrower.dashboard.referral_title') }}</h2>
                    <p class="text-sm text-white/80 mt-2">{{ __('borrower.referrals.your_code') }}: <span class="font-mono font-bold text-white">{{ $referralCode }}</span></p>
                    <p class="text-sm text-white/80 mt-1">{{ __('borrower.dashboard.referral_wallet') }}: <span class="font-bold text-brand-gold">{{ number_format(wallet_balance_as_points($referralWallet->balance ?? 0)) }} {{ __('borrower.rewards.points_short') }}</span></p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 shrink-0">
                    <x-site.referral-share :link="$referralLink" :code="$referralCode" :message="$referralShareMessage ?? null" />
                    <a href="{{ route('site.borrower.engagement', ['tab' => 'referrals']) }}" class="inline-flex justify-center bg-white/10 hover:bg-white/20 text-white font-semibold px-5 py-2.5 rounded-xl text-sm ring-1 ring-white/20">
                        {{ __('borrower.nav.referrals') }} →
                    </a>
                </div>
            </div>
        </section>
    @endif

    <div class="mb-8" id="loan-products">
        <div class="flex items-end justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-semibold">{{ __('borrower.loan_products') }}</h2>
                <p class="text-sm text-gray-500">{{ __('borrower.dashboard.browse_products') }}</p>
            </div>
            <a href="{{ route('site.borrower.loan-products') }}" class="text-xs font-semibold text-brand hover:underline">{{ __('borrower.dashboard.view_all') }}</a>
        </div>
        @if(isset($products) && $products->isNotEmpty())
            <div class="relative -mx-4 lg:mx-0" x-data="{ open: null }">
                <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory pb-2 items-stretch">
                    @foreach($products as $p)
                        <x-site.loan-product-card :product="$p" />
                    @endforeach
                </div>
            </div>
        @else
            <div class="text-sm text-gray-500">{{ __('borrower.dashboard_page.no_products') }}</div>
        @endif
    </div>

</x-site.borrower-layout>
