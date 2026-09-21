@php
    $paymentsQ = trim((string) ($dossier['payments_query'] ?? request('payments_q', '')));
@endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Money movement</p>
            <h4 class="text-base font-bold text-gray-900 mt-0.5">Payments</h4>
            <p class="text-xs text-gray-500 mt-0.5">This member only — search reference, application, loan, purpose, amount, or status.</p>
        </div>
        <form method="GET" action="{{ route('admin.customers.show', $customer) }}" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" value="payments">
            <label class="sr-only" for="payments_q">Search payments</label>
            <input id="payments_q" type="search" name="payments_q" value="{{ $paymentsQ }}"
                   placeholder="PAY-… / APP-… / amount / status"
                   class="rounded-lg border-gray-200 text-sm w-56 sm:w-72 focus:border-brand focus:ring-brand">
            <button type="submit" class="rounded-lg bg-brand text-white text-xs font-semibold px-3 py-2 hover:bg-brand-light">Search</button>
            @if ($paymentsQ !== '')
                <a href="{{ route('admin.customers.show', [$customer, 'tab' => 'payments']) }}" class="text-xs font-semibold text-gray-600 hover:underline">Clear</a>
            @endif
        </form>
    </div>

@if ($dossier['payments']->isEmpty())
    <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-5 py-12 text-center">
        <p class="text-sm font-semibold text-gray-700">{{ $paymentsQ !== '' ? 'No payments match this search' : 'No payments on file' }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ $paymentsQ !== '' ? 'Try another reference, amount, or status.' : 'Registration, fees, and loan repayments will show here.' }}</p>
    </div>
@else
        <div class="space-y-3">
            @foreach ($dossier['payments'] as $payment)
                @php
                    $when = $payment->adminOccurredAt();
                    $status = (string) ($payment->status ?? '');
                    $statusTone = match (true) {
                        in_array($status, ['completed', 'success', 'successful', 'paid', 'verified'], true) => 'bg-emerald-100 text-emerald-800',
                        in_array($status, ['failed', 'cancelled', 'canceled', 'rejected'], true) => 'bg-red-100 text-red-800',
                        default => 'bg-amber-100 text-amber-900',
                    };
                    $ctx = $payment->adminContext();
                @endphp
                <article class="rounded-2xl ring-1 ring-brand/10 bg-white px-4 py-4 sm:px-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ $ctx['type'] ?? config("payment_types.types.{$payment->payment_type}.label", $payment->payment_type) }}</p>
                            <p class="font-mono text-xs text-gray-500 mt-0.5 break-all">{{ $payment->reference }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-lg font-bold tabular-nums text-gray-900">{{ format_money((float) $payment->amount) }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ format_app_date($when) }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statusTone }}">
                            {{ display_label($payment->status, 'payment_status') }}
                        </span>
                        <a href="{{ route('admin.payments.show', $payment) }}" class="text-xs font-semibold text-brand hover:underline">Open →</a>
                    </div>
                    @if (! empty($ctx['for']) || ! empty($ctx['application_url']) || ! empty($ctx['loan_url']))
                        <div class="mt-3 rounded-xl bg-brand-muted/30 px-3 py-2.5 text-sm">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">For</p>
                            @if (! empty($ctx['for']))
                                @if (! empty($ctx['for_url']))
                                    <a href="{{ $ctx['for_url'] }}" class="font-medium text-brand hover:underline mt-0.5 inline-block">{{ $ctx['for'] }} →</a>
                                @else
                                    <p class="font-medium text-gray-900 mt-0.5">{{ $ctx['for'] }}</p>
                                @endif
                            @elseif (! empty($ctx['product']))
                                <p class="font-medium text-gray-900 mt-0.5">{{ $ctx['product'] }}</p>
                            @endif
                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                @if (! empty($ctx['application_url']) && empty($ctx['for']))
                                    <a href="{{ $ctx['application_url'] }}" class="font-semibold text-brand hover:underline">{{ $ctx['application_number'] }} →</a>
                                @endif
                                @if (! empty($ctx['loan_url']) && (empty($ctx['for']) || ! str_contains((string) $ctx['for'], (string) ($ctx['loan_number'] ?? '—'))))
                                    <a href="{{ $ctx['loan_url'] }}" class="font-semibold text-brand hover:underline">{{ $ctx['loan_number'] }} →</a>
                                @endif
                                @if (! empty($ctx['for_secondary']))
                                    <span class="text-gray-600">{{ $ctx['for_secondary'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
@endif
</div>
