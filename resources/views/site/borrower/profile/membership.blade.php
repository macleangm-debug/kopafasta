<x-site.borrower-layout :title="brand_title(__('borrower.profile.panel_membership'))" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.panel_membership'),
            'subtitle' => __('borrower.membership.card_subtitle'),
            'customer' => $customer,
            'active' => 'personal',
            'accountPanel' => 'membership',
            'wizardMode' => false,
        ])

        <x-site.member-card :customer="$customer" class="mb-8" />

        <section class="mb-8 glass-card overflow-hidden relative">
            <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
            <div class="relative p-6 sm:p-8">
                <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.membership.share_marketing_eyebrow') }}</p>
                <h2 class="text-xl font-bold text-gray-900 mt-2">{{ __('borrower.membership.share_marketing_title') }}</h2>
                <p class="text-sm text-gray-600 mt-2 max-w-2xl">{{ __('borrower.membership.share_marketing_body') }}</p>
                <p class="mt-4 text-xs text-gray-500">{{ __('borrower.membership.share_marketing_hint') }}</p>
            </div>
        </section>

        @if ($referralCode ?? null)
            <section class="mb-8 bg-brand text-white rounded-2xl p-6 sm:p-8 shadow-lg relative overflow-hidden">
                <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
                <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.membership_page.referral_program') }}</p>
                        <p class="text-sm text-white/90 mt-2">{{ __('borrower.membership_page.referral_summary', ['code' => $referralCode, 'balance' => format_money($referralWallet->balance ?? 0)]) }}</p>
                    </div>
                    <a href="{{ route('site.borrower.engagement', ['tab' => 'referrals']) }}" class="shrink-0 inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                        {{ __('borrower.membership_page.open_referrals') }}
                    </a>
                </div>
            </section>
        @endif
    </div>

</x-site.borrower-layout>
