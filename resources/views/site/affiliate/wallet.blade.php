<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.wallet_title'))" active="wallet">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('site.affiliate_portal.wallet_title') }}</h1>
        <p class="text-sm text-gray-600 mt-1">{{ __('site.affiliate_portal.wallet_subtitle') }}</p>
    </div>

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
        <form method="POST" action="{{ route('site.affiliate.wallet.payout-request') }}" class="glass-card p-6 mb-6 space-y-4">
            @csrf
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.request_payout') }}</h2>
            <p class="text-sm text-gray-600">{{ __('site.affiliate_portal.available_balance', ['amount' => format_money($available)]) }}</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.payout_amount') }}</label>
                    <input type="number" name="amount" min="{{ (int) $minPayout }}" max="{{ (int) $available }}" step="1000" required
                           value="{{ old('amount', (int) max($minPayout, $available)) }}"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    <p class="text-xs text-gray-500 mt-1">{{ __('site.affiliate_portal.min_payout_note', ['amount' => format_money($minPayout)]) }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.payout_notes') }}</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
            </div>
            <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-full text-sm">{{ __('site.affiliate_portal.submit_payout') }}</button>
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
            <p class="px-5 py-8 text-sm text-gray-500 text-center">{{ __('site.affiliate_portal.no_payments') }}</p>
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
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs">{{ $payment->invoice_number ?? '#'.$payment->id }}</td>
                                <td class="px-4 py-3 tabular-nums font-semibold">{{ format_money($payment->amount) }}</td>
                                <td class="px-4 py-3 capitalize">{{ $payment->status }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if (in_array($payment->status, ['pending', 'approved'], true))
                                        <details class="inline-block text-left">
                                            <summary class="text-xs font-semibold text-red-600 cursor-pointer">{{ __('site.affiliate_portal.dispute') }}</summary>
                                            <form method="POST" action="{{ route('site.affiliate.wallet.dispute', $payment) }}" class="mt-2 p-3 bg-gray-50 rounded-lg w-64">
                                                @csrf
                                                <textarea name="reason" required rows="2" class="w-full text-xs rounded border-gray-300 mb-2" placeholder="{{ __('site.affiliate_portal.dispute_reason') }}"></textarea>
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
