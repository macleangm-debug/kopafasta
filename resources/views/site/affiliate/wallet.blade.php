<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.wallet_title'))" active="wallet">

    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('site.affiliate_portal.nav_wallet') }}</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-1">{{ __('site.affiliate_portal.wallet_title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('site.affiliate_portal.wallet_subtitle') }}</p>
    </div>

    <section class="mb-6 bg-brand text-white rounded-2xl p-6 sm:p-8 shadow-lg relative overflow-hidden">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.available_balance_label') }}</p>
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
            ['pending', $summary['pending'] ?? 0, 'text-amber-700'],
            ['approved', $summary['approved'] ?? 0, 'text-emerald-700'],
            ['paid', $summary['paid'] ?? 0, 'text-brand'],
            ['disputed', $summary['disputed'] ?? 0, 'text-red-700'],
        ] as [$key, $amount, $color])
            <div class="glass-card p-4">
                <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ __("site.affiliate_portal.{$key}") }}</p>
                <p class="text-lg font-bold mt-1 tabular-nums {{ $color }}">{{ format_money($amount) }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ ($summary['counts'][$key] ?? 0).' '.__('site.affiliate_portal.items') }}</p>
            </div>
        @endforeach
    </div>

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
                :action-label="__('site.affiliate_portal.go_dashboard')"
                :action-url="route('site.affiliate.dashboard')"
            />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-widest text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('site.affiliate_portal.col_invoice') }}</th>
                            <th class="px-4 py-3">{{ __('site.affiliate_portal.col_amount') }}</th>
                            <th class="px-4 py-3">{{ __('site.affiliate_portal.col_status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($payments as $payment)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-4 py-3 font-mono text-xs">{{ $payment->invoice_number ?? '#'.$payment->id }}</td>
                                <td class="px-4 py-3 tabular-nums font-semibold">{{ format_money($payment->amount) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full px-2.5 py-1 ring-1
                                        {{ match($payment->status) {
                                            'approved' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                                            'paid' => 'bg-sky-100 text-sky-800 ring-sky-200',
                                            'disputed' => 'bg-red-100 text-red-800 ring-red-200',
                                            default => 'bg-amber-100 text-amber-900 ring-amber-200',
                                        } }}">
                                        {{ ucfirst($payment->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if (in_array($payment->status, ['pending', 'approved'], true))
                                        <details class="inline-block text-left">
                                            <summary class="text-xs font-semibold text-red-600 cursor-pointer">{{ __('site.affiliate_portal.dispute') }}</summary>
                                            <form method="POST" action="{{ route('site.affiliate.wallet.dispute', $payment) }}" class="mt-2 p-3 bg-gray-50 rounded-xl w-64 ring-1 ring-gray-100">
                                                @csrf
                                                <textarea name="reason" required rows="2" class="w-full text-xs rounded-lg border-gray-200 mb-2 px-2 py-1.5" placeholder="{{ __('site.affiliate_portal.dispute_reason') }}"></textarea>
                                                <button type="submit" class="text-xs font-semibold text-red-700">{{ __('site.affiliate_portal.submit_dispute') }}</button>
                                            </form>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $payments->links() }}</div>
            @endif
        @endif
    </div>

</x-site.affiliate-layout>
