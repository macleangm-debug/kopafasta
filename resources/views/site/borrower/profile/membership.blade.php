<x-site.borrower-layout :title="brand_title(__('borrower.profile.panel_membership'))" active="membership" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.panel_membership'),
            'subtitle' => __('borrower.membership.card_subtitle'),
            'customer' => $customer,
            'active' => 'personal',
            'accountPanel' => 'membership',
            'wizardMode' => false,
        ])

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('warning'))
            <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
        @endif

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

        <div class="glass-card overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100/80">
                <h2 class="font-bold text-gray-900">{{ __('borrower.membership_page.history_title') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/80 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ __('borrower.membership_page.col_date') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('borrower.membership_page.col_event') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('borrower.membership_page.col_issued') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('borrower.membership_page.col_expires') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('borrower.membership_page.col_renewals') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('borrower.membership_page.col_payment_ref') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($history as $h)
                            <tr class="hover:bg-brand-muted/20">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $h->created_at->format('d M Y') }}</td>
                                <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $h->event) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ optional($h->issued_at)->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ optional($h->expires_at)->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $h->renewal_count_after ?? '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $h->payment_reference ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-gray-500">{{ __('borrower.membership_page.history_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</x-site.borrower-layout>
