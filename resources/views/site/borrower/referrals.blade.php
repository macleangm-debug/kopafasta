<x-site.borrower-layout :title="brand_title(__('borrower.referrals.title'))" active="referrals" content-width="wide">

    <x-site.borrower-page-header
        :eyebrow="__('borrower.referrals.grow')"
        :title="__('borrower.referrals.title')"
        :subtitle="__('borrower.referrals.subtitle')"
    />

    <x-site.page-loading-shell>
        <x-slot:skeleton>
            <div class="grid gap-6 lg:grid-cols-3">
                <x-site.skeleton-card :lines="5" class="lg:col-span-2" />
                <x-site.skeleton-card :lines="3" />
            </div>
        </x-slot:skeleton>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2 bg-brand text-white rounded-2xl p-6 sm:p-8 shadow-lg relative overflow-hidden">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
            <div class="relative">
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.referrals.your_code') }}</p>
                <p class="mt-2 text-3xl sm:text-4xl font-mono font-bold tracking-wide">{{ $referralCode }}</p>

                <p class="mt-6 text-xs uppercase tracking-widest text-white/70 font-semibold">{{ __('borrower.referrals.your_link') }}</p>
                <p class="mt-2 text-sm break-all text-white/90 bg-white/10 rounded-xl px-4 py-3 ring-1 ring-white/15 font-mono">{{ $referralLink }}</p>

                <div class="mt-6">
                    <x-site.referral-share :link="$referralLink" :code="$referralCode" :message="$referralShareMessage ?? null" />
                </div>

                <p class="mt-6 text-xs text-white/70">
                    {{ __('borrower.referrals.friend_discount', [
                        'discount' => format_number($referralSettings['discount_percent'], 0),
                        'commission' => format_number($referralSettings['commission_percent'], 0),
                    ]) }}
                </p>
            </div>
        </section>

        <section class="relative overflow-hidden rounded-2xl ring-1 ring-brand/20 bg-gradient-to-br from-brand-muted/60 via-white to-brand-gold/10 shadow-sm p-6 flex flex-col">
            <div class="absolute -right-6 -top-6 size-24 rounded-full bg-brand-gold/10"></div>
            <div class="relative">
                <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.referrals.wallet') }}</p>
                <p class="mt-3 text-4xl font-black text-gray-900 tabular-nums leading-none">{{ format_money($referralWallet->balance ?? 0) }}</p>
                <p class="text-sm text-gray-600 mt-3">{{ __('borrower.referrals.wallet_desc') }}</p>
                <div class="mt-5 rounded-xl bg-white/80 ring-1 ring-gray-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.referrals.wallet_limit_label') }}</p>
                    <p class="text-xs text-gray-700 mt-1">{{ __('borrower.referrals.wallet_limit', ['percent' => format_number($referralSettings['wallet_max_fee_percent'], 0)]) }}</p>
                </div>
            </div>
            <a href="{{ route('site.borrower.profile', ['section' => 'membership']) }}" class="relative mt-auto pt-6 inline-flex items-center justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                {{ __('borrower.referrals.view_membership') }} →
            </a>
        </section>
    </div>

    <div class="mt-8 grid gap-6 sm:grid-cols-2">
        <section class="glass-card p-6">
            <h2 class="font-semibold text-emerald-800">{{ __('borrower.referrals.wallet_can_pay') }}</h2>
            <ul class="mt-3 space-y-2 text-sm text-gray-700">
                @foreach ($walletRules['allowed'] as $rule)
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 shrink-0">✓</span>
                        <span>{{ $rule }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="glass-card p-6">
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

    </x-site.page-loading-shell>

</x-site.borrower-layout>
