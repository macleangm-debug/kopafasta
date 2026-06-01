<x-site.borrower-layout :title="brand_title(__('borrower.referrals.title'))" active="referrals">

    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ __('borrower.referrals.grow') }}</p>
        <h1 class="text-2xl sm:text-3xl font-bold">{{ __('borrower.referrals.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('borrower.referrals.subtitle') }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2 bg-gradient-to-br from-indigo-700 via-indigo-800 to-slate-900 text-white rounded-2xl p-6 sm:p-8 shadow-lg">
            <p class="text-xs uppercase tracking-widest text-indigo-200 font-semibold">{{ __('borrower.referrals.your_code') }}</p>
            <p class="mt-2 text-3xl sm:text-4xl font-mono font-bold tracking-wide">{{ $referralCode }}</p>

            <p class="mt-6 text-xs uppercase tracking-widest text-indigo-200 font-semibold">{{ __('borrower.referrals.your_link') }}</p>
            <p class="mt-2 text-sm break-all text-indigo-100 bg-white/10 rounded-xl px-4 py-3">{{ $referralLink }}</p>

            <div class="mt-6">
                <x-site.referral-share :link="$referralLink" :code="$referralCode" />
            </div>

            <p class="mt-6 text-xs text-indigo-200">
                {{ __('borrower.referrals.friend_discount', [
                    'discount' => number_format($referralSettings['discount_percent'], 0),
                    'commission' => number_format($referralSettings['commission_percent'], 0),
                ]) }}
            </p>
        </section>

        <section class="bg-white rounded-2xl ring-1 ring-gray-200 p-6 flex flex-col">
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.referrals.wallet') }}</p>
            <p class="mt-3 text-3xl font-bold text-gray-900">TZS {{ number_format($referralWallet->balance ?? 0) }}</p>
            <p class="text-sm text-gray-500 mt-2">{{ __('borrower.referrals.wallet_desc') }}</p>
            <a href="{{ route('site.membership.show') }}" class="mt-auto pt-6 text-xs font-semibold text-amber-700 hover:underline">{{ __('borrower.referrals.view_membership') }}</a>
        </section>
    </div>

    <div class="mt-8 grid gap-6 sm:grid-cols-2">
        <section class="bg-white rounded-2xl ring-1 ring-gray-200 p-6">
            <h2 class="font-semibold text-emerald-800">{{ __('borrower.referrals.wallet_can_pay') }}</h2>
            <ul class="mt-3 space-y-2 text-sm text-gray-700">
                @foreach ($walletRules['allowed'] as $rule)
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 shrink-0">✓</span>
                        <span>{{ $rule }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 text-xs text-gray-500">{{ __('borrower.referrals.wallet_limit', ['percent' => number_format($referralSettings['wallet_max_fee_percent'], 0)]) }}</p>
        </section>

        <section class="bg-white rounded-2xl ring-1 ring-gray-200 p-6">
            <h2 class="font-semibold text-red-800">{{ __('borrower.referrals.wallet_cannot_pay') }}</h2>
            <ul class="mt-3 space-y-2 text-sm text-gray-700">
                @foreach ($walletRules['blocked'] as $rule)
                    <li class="flex items-start gap-2">
                        <span class="text-red-500 shrink-0">✕</span>
                        <span>{{ $rule }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

</x-site.borrower-layout>
