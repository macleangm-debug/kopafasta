<x-site.borrower-layout :title="brand_title(__('borrower.profile.panel_membership'))" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.panel_membership'),
            'subtitle' => null,
            'customer' => $customer,
            'active' => 'personal',
            'accountPanel' => 'membership',
            'wizardMode' => false,
        ])

        <section class="relative overflow-hidden rounded-[1.75rem] mb-6">
            <div class="absolute inset-0 kf-premium-panel opacity-95"></div>
            <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_top_right,_rgba(245,200,66,0.45),_transparent_55%)] pointer-events-none"></div>
            <div class="relative px-5 sm:px-7 py-5 sm:py-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.2em] font-bold text-brand-gold">{{ brand_name() }}</p>
                    <h1 class="mt-1 text-2xl sm:text-3xl font-extrabold tracking-tight text-white">{{ __('borrower.membership.my_card') }}</h1>
                    <p class="mt-1.5 text-sm text-white/80 max-w-xl">{{ __('borrower.membership.card_subtitle') }}</p>
                </div>
                @php
                    $gradeKey = strtoupper(strtolower((string) ($customer->grade ?: 'bronze')));
                    $plusActive = app(\App\Services\Plus\PlusService::class)->isActive($customer);
                @endphp
                <span class="inline-flex items-center self-start sm:self-auto rounded-full bg-white/15 text-white px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em] ring-1 ring-brand-gold/40">
                    {{ $gradeKey }}{{ $plusActive ? ' · '.__('plus.card.plus') : '' }}
                </span>
            </div>
        </section>

        <x-site.member-card
            :customer="$customer"
            :referral-code="$referralCode ?? null"
            :referral-link="$referralLink ?? null"
            class="mb-8"
        />
    </div>

</x-site.borrower-layout>
