<x-site.borrower-layout :title="brand_title('Dashboard')" active="dashboard" content-width="wide">

    @php
        $hero = $dashboardHero ?? [];
        $fullName = trim((string) ($customer->full_name ?? Auth::user()->name ?? ''));
        $hero['greeting'] = $fullName !== '' ? $fullName : (__('borrower.welcome').', '.(explode(' ', (string) (Auth::user()->name ?? ''))[0] ?? ''));
        $hero['membership_no'] = $customer->member_no ?? null;
        $hero['grade'] = $customer->grade ?? 'bronze';
        $hero['plus_active'] = (bool) ($plusActive ?? false);
        $gradeAccess = app(\App\Services\Grades\GradeBenefitService::class)->potentialAccess($customer);
        if ($gradeAccess > 0) {
            $hero['eligibility_amount'] = format_money($gradeAccess);
            $hero['eligibility_hint'] = __('borrower.dashboard.eligibility_growth_hint_short');
        }

        // Home hero is greeting + eligibility — never loan application tracking.
        if (in_array($hero['variant'] ?? '', ['under_review', 'applications', 'no_loan'], true)) {
            $hero['title'] = null;
            $hero['subtitle'] = null;
            $hero['meta'] = null;
            $hero['cta_label'] = null;
            $hero['cta_url'] = null;
            $hero['secondary_cta_label'] = null;
            $hero['secondary_cta_url'] = null;
        }

        if (! empty($hero['membership_no'])) {
            $hero['cta_label'] = __('borrower.membership.my_card');
            $hero['cta_url'] = route('site.borrower.profile', ['section' => 'membership']);
        }

        $hero['tertiary_cta_label'] = __('borrower.nav.verify');
        $hero['tertiary_cta_url'] = route('site.borrower.verify');
    @endphp

    <x-site.borrower-dashboard-hero :hero="$hero" />

    <x-site.borrower-dashboard-quick-actions :active-loan="$activeLoan ?? null" />

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

    @php
        $underReview = in_array((string) ($customer->grade_status ?? ''), ['under_review'], true)
            || in_array((string) ($customer->grade_integrity ?? ''), ['review'], true);
        $plusIsOn = (bool) ($plusActive ?? false);
        $plusNeedsRenewal = (bool) ($plusNeedsRenewal ?? false);
    @endphp
    <section class="mb-6 kf-premium-panel rounded-2xl p-5 sm:p-6">
        <div class="relative flex flex-wrap items-start justify-between gap-3">
            <x-site.brand-mark size="sm" variant="light" />
            <x-site.grade-badge :grade="$customer->grade ?? 'bronze'" :plus="$plusIsOn" size="lg" />
        </div>
        @if (! empty($customer->member_no))
            <p class="relative mt-4 text-sm text-white/80">{{ __('plus.card.member', ['id' => $customer->member_no]) }}</p>
        @endif
        @if ($underReview)
            <p class="relative font-semibold mt-2">{{ __('plus.card.reviewing') }}</p>
        @else
            <p class="relative font-semibold mt-2 text-lg">{{ __('plus.card.trust', ['percent' => $trust['percent'] ?? 0, 'label' => $trust['label'] ?? '']) }}</p>
        @endif
        <p class="relative text-sm text-white/80 mt-2 max-w-xl">{{ __('plus.card.teaser_body') }}</p>
        <div class="relative mt-4 flex flex-wrap gap-2">
            @if ($plusIsOn && $plusNeedsRenewal)
                <form method="post" action="{{ route('site.borrower.plus.renew') }}">
                    @csrf
                    <button class="inline-flex rounded-xl bg-brand-gold hover:brightness-95 text-brand px-5 py-2.5 text-sm font-bold shadow-sm ring-1 ring-brand-gold/40">
                        {{ __('plus.home.renew') }}
                    </button>
                </form>
                <a href="{{ route('site.borrower.plus.home') }}"
                   class="inline-flex rounded-xl bg-white/10 hover:bg-white/20 text-white px-5 py-2.5 text-sm font-bold ring-1 ring-white/20">
                    {{ __('plus.card.open') }}
                </a>
            @elseif ($plusIsOn)
                <a href="{{ route('site.borrower.plus.home') }}"
                   class="inline-flex rounded-xl bg-brand-gold hover:brightness-95 text-brand px-5 py-2.5 text-sm font-bold shadow-sm ring-1 ring-brand-gold/40">
                    {{ __('plus.card.open') }}
                </a>
            @else
                <a href="{{ route('site.borrower.plus.home') }}"
                   class="inline-flex rounded-xl bg-brand-gold hover:brightness-95 text-brand px-5 py-2.5 text-sm font-bold shadow-sm ring-1 ring-brand-gold/40">
                    {{ __('plus.card.explore') }}
                </a>
            @endif
        </div>
    </section>

    @if (! empty($financialHealth))
        <x-site.borrower-financial-health :health="$financialHealth" />
    @endif

    <x-site.borrower-financial-snapshot :snapshot="$financialSnapshot ?? []" />

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
        @php $referralPoints = wallet_balance_as_points($referralWallet->balance ?? 0); @endphp
        <section class="mb-8 kf-premium-panel rounded-2xl p-6 sm:p-8">
            <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.referrals.grow') }}</p>
                    <h2 class="text-xl sm:text-2xl font-bold mt-1">{{ __('borrower.dashboard.referral_title') }}</h2>
                    <p class="text-sm text-white/80 mt-2">{{ __('borrower.referrals.your_code') }}: <span class="font-mono font-bold text-white">{{ $referralCode }}</span></p>
                    <div class="mt-4">
                        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.dashboard.referral_wallet') }}</p>
                        <p class="mt-1 flex items-end gap-2">
                            <span class="text-5xl sm:text-6xl font-black tabular-nums text-brand-gold leading-none">{{ number_format($referralPoints) }}</span>
                            <span class="pb-1 text-sm font-semibold text-white/80">{{ __('borrower.rewards.points_short') }}</span>
                        </p>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 shrink-0">
                    <x-site.referral-share :link="$referralLink" :code="$referralCode" :message="$referralShareMessage ?? null" :channels="['whatsapp']" />
                    <a href="{{ route('site.borrower.engagement', ['tab' => 'referrals']) }}" class="inline-flex items-center justify-center bg-white/10 hover:bg-white/20 text-white font-semibold px-5 py-2.5 rounded-xl text-sm ring-1 ring-white/20">
                        {{ __('borrower.nav.referrals') }}
                    </a>
                </div>
            </div>
        </section>
    @endif

</x-site.borrower-layout>
