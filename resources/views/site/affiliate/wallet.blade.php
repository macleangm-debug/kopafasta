<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.wallet_title'))" active="wallet">

    <x-site.borrower-page-header
        :eyebrow="__('site.affiliate_portal.nav_wallet')"
        :title="__('site.affiliate_portal.wallet_title')"
        :subtitle="__('site.affiliate_portal.wallet_subtitle')"
    />

    <section class="mb-6 kf-premium-panel rounded-2xl p-6 sm:p-8 relative">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.hero_available') }}</p>
                <p class="text-3xl sm:text-4xl font-bold mt-1 tabular-nums">{{ format_money($available) }}</p>
                <p class="text-sm text-white/70 mt-2">{{ __('site.affiliate_portal.min_payout_note', ['amount' => format_money($minPayout)]) }}</p>
            </div>
            @if ($available >= $minPayout)
                <a href="#payout-form" class="inline-flex justify-center bg-white text-brand font-semibold px-6 py-3 rounded-xl text-sm shrink-0 hover:bg-brand-gold transition">
                    {{ __('site.affiliate_portal.request_payout') }}
                </a>
            @endif
        </div>
    </section>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @foreach ([
            ['available', $totals['available'], 'text-emerald-700'],
            ['pending', $totals['pending'], 'text-amber-700'],
            ['total_earned', $totals['earned'], 'text-brand'],
            ['withdrawn', $totals['withdrawn'], 'text-gray-900'],
        ] as [$key, $amount, $color])
            <div class="glass-card p-4">
                <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ __("site.affiliate_portal.{$key}") }}</p>
                <p class="text-lg font-bold mt-1 tabular-nums {{ $color }}">{{ format_money($amount) }}</p>
            </div>
        @endforeach
    </div>

    <section class="glass-card p-6 mb-6 space-y-3">
        <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.how_i_earn') }}</h2>
        <dl class="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">{{ __('site.affiliate_portal.your_commission') }}</dt>
                                <dd class="font-semibold text-gray-900 mt-1">{{ number_format($earnings['commission_percent'], 1) }}% · {{ $earnings['commission_mode_label'] }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('site.affiliate_portal.eligible_business') }}</dt>
                <dd class="font-semibold text-gray-900 mt-1">{{ implode(', ', $earnings['qualifying_events']) ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('site.affiliate_portal.when_available') }}</dt>
                <dd class="font-semibold text-gray-900 mt-1">{{ $earnings['settlement'] }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('site.affiliate_portal.minimum_withdrawal') }}</dt>
                <dd class="font-semibold text-gray-900 mt-1">{{ $earnings['minimum_withdrawal'] }}</dd>
            </div>
        </dl>
    </section>

    @if ($available >= $minPayout)
        <form id="payout-form" method="POST" action="{{ route('site.affiliate.wallet.payout-request') }}" class="glass-card p-6 mb-6 space-y-4 scroll-mt-24">
            @csrf
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.request_payout') }}</h2>
            <p class="text-sm text-gray-600">{{ __('site.affiliate_portal.available_balance', ['amount' => format_money($available)]) }}</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.payout_amount') }}</label>
                    <input type="number" name="amount" min="{{ (int) $minPayout }}" max="{{ (int) $available }}" step="1000" required
                           value="{{ old('amount', (int) max($minPayout, $available)) }}"
                           class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.payout_notes') }}</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500"
                           class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                </div>
            </div>
            <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.affiliate_portal.submit_payout') }}</button>
        </form>
    @else
        <div class="glass-card p-5 mb-6 text-sm text-gray-600">
            {{ __('site.affiliate_portal.payout_not_ready', ['amount' => format_money($minPayout), 'available' => format_money($available)]) }}
        </div>
    @endif

    <div class="glass-card overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">{{ __('site.affiliate_portal.payment_history') }}</h2>
        </div>
        @if ($payments->isEmpty())
            <x-site.empty-state
                icon="💰"
                :title="__('site.affiliate_portal.no_payments')"
                :description="__('site.affiliate_portal.no_payments_hint')"
                :action-label="__('site.affiliate_portal.nav_share')"
                :action-url="route('site.affiliate.share')"
            />
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($payments as $payment)
                    <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-gray-500">{{ $payment->invoice_number ?? '#'.$payment->id }}</p>
                            <p class="font-semibold tabular-nums mt-1">{{ format_money($payment->amount) }}</p>
                        </div>
                        <span class="inline-flex self-start text-[10px] font-bold uppercase tracking-wide rounded-full px-2.5 py-1 ring-1
                            {{ match($payment->status) {
                                'approved' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                                'paid' => 'bg-sky-100 text-sky-800 ring-sky-200',
                                'disputed' => 'bg-red-100 text-red-800 ring-red-200',
                                default => 'bg-amber-100 text-amber-900 ring-amber-200',
                            } }}">
                            {{ __('site.affiliate_portal.'.$payment->status) }}
                        </span>
                    </div>
                @endforeach
            </div>
            @if ($payments->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $payments->links() }}</div>
            @endif
        @endif
    </div>

</x-site.affiliate-layout>
