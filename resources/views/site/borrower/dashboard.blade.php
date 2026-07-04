<x-site.borrower-layout :title="brand_title('Dashboard')" active="dashboard" content-width="wide">

    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.welcome') }}</p>
        <h1 class="text-2xl sm:text-3xl font-bold mt-1">Habari, {{ $customer->first_name ?? Auth::user()->name }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('borrower.dashboard.customer_number', ['number' => $customer->customer_number ?? '—']) }}</p>
    </div>

    <x-site.borrower-dashboard-hero :hero="$dashboardHero ?? []" />

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

    @if (($openDocumentRequests ?? collect())->isNotEmpty())
        @php $firstDocRequest = $openDocumentRequests->first(); @endphp
        <div class="mb-6 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-sky-900">{{ __('borrower.dashboard.document_requests_title') }}</p>
                <p class="text-xs text-sky-800 mt-1">{{ __('borrower.dashboard.document_requests_body', ['count' => $openDocumentRequests->count()]) }}</p>
            </div>
            @if ($firstDocRequest?->application)
                <a href="{{ route('site.borrower.application', $firstDocRequest->application) }}"
                   class="text-sm font-semibold text-sky-900 hover:underline shrink-0">{{ __('borrower.dashboard.document_requests_cta') }}</a>
            @endif
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
                    <p class="text-sm text-white/80 mt-1">{{ __('borrower.dashboard.referral_wallet') }}: <span class="font-bold text-brand-gold">{{ format_money($referralWallet->balance ?? 0) }}</span></p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 shrink-0">
                    <x-site.referral-share :link="$referralLink" :code="$referralCode" :message="$referralShareMessage ?? null" />
                    <a href="{{ route('site.borrower.referrals') }}" class="inline-flex justify-center bg-white/10 hover:bg-white/20 text-white font-semibold px-5 py-2.5 rounded-xl text-sm ring-1 ring-white/20">
                        {{ __('borrower.nav.referrals') }} →
                    </a>
                </div>
            </div>
        </section>
    @endif

    <div class="mb-8">
        <div class="flex items-end justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-semibold">{{ __('borrower.loan_products') }}</h2>
                <p class="text-sm text-gray-500">{{ __('borrower.dashboard.browse_products') }}</p>
            </div>
            <a href="{{ route('site.borrower.marketplace') }}" class="text-xs font-semibold text-brand hover:underline">{{ __('borrower.dashboard.marketplace_link') }}</a>
        </div>
        @if(isset($products) && $products->isNotEmpty())
            <div class="relative -mx-4 lg:mx-0" x-data="{ open: null }">
                <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory px-4 lg:px-0 pb-2">
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
