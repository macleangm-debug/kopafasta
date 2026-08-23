<x-admin.layout title="Payments" heading="" subheading="">

    <section class="mb-6">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-6 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Collections desk</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Payments</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    This page is money in only — completed borrower payments and bank deposits waiting for a match.
                    Money out (payouts and disbursements) lives on the Money ledger.
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('admin.payments.ledger', ['direction' => 'in', 'status' => 'verified']) }}"
                       class="inline-flex items-center rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/25 hover:bg-white/15">
                        Money ledger · Incoming →
                    </a>
                    <a href="{{ route('admin.payments.ledger', ['direction' => 'out']) }}"
                       class="inline-flex items-center rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/25 hover:bg-white/15">
                        Money ledger · Outgoing →
                    </a>
                </div>
            </div>

            {{-- Primary money totals: complete in vs complete out --}}
            <div class="bg-white px-6 py-5 grid sm:grid-cols-2 gap-4 border-b border-gray-100">
                <a href="{{ route('admin.payments.index', ['status' => 'complete']) }}"
                   class="rounded-xl bg-emerald-50 ring-1 ring-emerald-100 px-5 py-4 hover:ring-emerald-200 transition {{ $status === 'complete' ? 'ring-2 ring-emerald-300' : '' }}">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">Incoming · complete</p>
                    <p class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ format_money((float) ($counts['complete_amount'] ?? 0)) }}</p>
                    <p class="text-xs text-gray-500 mt-1 tabular-nums">{{ number_format($counts['complete']) }} payments · list below</p>
                </a>
                <a href="{{ route('admin.payments.ledger', ['direction' => 'out']) }}"
                   class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/15 px-5 py-4 hover:ring-brand/30 transition">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Outgoing · complete</p>
                    <p class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ format_money((float) ($counts['outgoing_complete_amount'] ?? 0)) }}</p>
                    <p class="text-xs text-gray-500 mt-1 tabular-nums">{{ number_format($counts['outgoing_complete'] ?? 0) }} payouts · open Money ledger →</p>
                </a>
            </div>

            {{-- Action / health counters for this desk --}}
            <div class="bg-white px-6 py-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <a href="{{ route('admin.payments.index', ['status' => 'awaiting_bank']) }}"
                   class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-3 hover:ring-amber-200 transition {{ $status === 'awaiting_bank' ? 'ring-2 ring-amber-300' : '' }}">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Awaiting bank verify</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ number_format($counts['awaiting_bank']) }}</p>
                    <p class="text-xs text-amber-900/70 mt-0.5">Staff action needed</p>
                </a>
                <div class="rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-sky-800 font-semibold">Completed today</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ number_format($counts['verified_today'] ?? 0) }}</p>
                </div>
                <a href="{{ route('admin.payments.index', ['status' => 'rejected']) }}"
                   class="rounded-xl bg-rose-50 ring-1 ring-rose-100 px-4 py-3 hover:ring-rose-200 transition {{ $status === 'rejected' ? 'ring-2 ring-rose-300' : '' }}">
                    <p class="text-[10px] uppercase tracking-widest text-rose-800 font-semibold">Rejected</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ number_format($counts['rejected']) }}</p>
                </a>
                <div class="rounded-xl {{ ($counts['missing_journal'] ?? 0) > 0 ? 'bg-rose-50 ring-rose-100' : 'bg-gray-50 ring-gray-100' }} ring-1 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest {{ ($counts['missing_journal'] ?? 0) > 0 ? 'text-rose-800' : 'text-gray-600' }} font-semibold">Missing journal</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ number_format($counts['missing_journal'] ?? 0) }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="mb-4 flex flex-wrap gap-2 items-center">
        <span class="text-[11px] uppercase tracking-widest text-gray-400 font-semibold mr-1">List</span>
        @foreach ([
            'complete' => 'Incoming complete',
            'awaiting_bank' => ($counts['awaiting_bank'] ?? 0).' awaiting bank',
            'rejected' => 'Rejected',
            'all' => 'All (incl. in-flight)',
        ] as $key => $label)
            <a href="{{ route('admin.payments.index', array_filter(['status' => $key, 'type' => $type ?: null])) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $status === $key ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
        <a href="{{ route('admin.membership-payments.index') }}"
           class="px-3 py-1.5 rounded-lg text-sm font-medium bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50">
            Membership queue →
        </a>
        @if (auth()->user()?->hasPermission('settings.manage'))
            <a href="{{ route('admin.settings.payment-accounts') }}"
               class="ml-auto text-sm font-semibold text-brand hover:text-brand-light self-center">
                Payment account settings
            </a>
        @endif
    </div>

    @if ($status === 'awaiting_bank')
        <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
            Bank deposits only — match proof to your collection account, then verify to post the ledger. Mobile money is confirmed by the PSP and appears under Incoming complete automatically.
        </div>
    @elseif ($status === 'complete')
        <div class="mb-4 rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 px-4 py-3 text-sm text-brand">
            Showing completed money in (PSP-approved mobile + verified bank). Application fees such as Asset Lending appear here once the payment is complete.
        </div>
    @endif

    <form method="GET" action="{{ route('admin.payments.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="flex-1 min-w-[16rem]">
            <label class="block text-[11px] font-medium text-gray-500 mb-1">Search</label>
            <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="Reference, member, phone, NIDA, amount, type…"
                   class="w-full rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-[11px] font-medium text-gray-500 mb-1">Payment type</label>
            <select name="type" class="rounded-lg border-gray-300 text-sm min-w-[12rem]">
                <option value="">All types</option>
                @foreach ($types as $key => $meta)
                    <option value="{{ $key }}" @selected(($type ?? '') === $key)>{{ $meta['label'] ?? $key }}</option>
                @endforeach
            </select>
        </div>
        <button class="text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2 rounded-xl">Search</button>
        @if (($type ?? '') || ($q ?? ''))
            <a href="{{ route('admin.payments.index', ['status' => $status]) }}" class="text-sm text-gray-500 hover:underline pb-2">Clear</a>
        @endif
    </form>

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3">Reference</th>
                        <th class="px-5 py-3">Borrower</th>
                        <th class="px-5 py-3">Type &amp; context</th>
                        <th class="px-5 py-3">Method</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($payments as $payment)
                        @php
                            $customer = $payment->customer;
                            $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
                            $ctx = $payment->adminContext();
                            $needsBankMatch = $payment->payment_method === 'bank_transfer'
                                && in_array($payment->status, ['pending_verification', 'clarification_requested'], true);
                        @endphp
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-5 py-3 text-xs text-gray-600 whitespace-nowrap">
                                <p class="font-semibold text-gray-900">{{ format_app_date($payment->adminOccurredAt()) }}</p>
                                <p class="tabular-nums text-gray-500 mt-0.5">{{ format_app_datetime($payment->adminOccurredAt(), 'H:i') }}</p>
                            </td>
                            <td class="px-5 py-3 font-mono text-xs font-semibold">
                                <a href="{{ route('admin.payments.show', $payment) }}" class="text-brand hover:text-brand-light">
                                    {{ $payment->reference }}
                                </a>
                            </td>
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $name ?: '—' }}</div>
                                <div class="text-xs text-gray-500">{{ $customer->customer_number ?? '' }}</div>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-gray-900">{{ $ctx['type'] }}</p>
                                @if ($ctx['product'])
                                    <p class="text-xs text-gray-700 mt-0.5">
                                        {{ $ctx['product'] }}
                                        @if ($ctx['product_code'])
                                            <span class="text-gray-400 font-mono">({{ $ctx['product_code'] }})</span>
                                        @endif
                                    </p>
                                @endif
                                <div class="mt-1 space-y-0.5 text-xs text-gray-500">
                                    @if ($ctx['application_number'])
                                        <p>
                                            Application
                                            @if ($ctx['application_url'])
                                                <a href="{{ $ctx['application_url'] }}" class="font-semibold text-brand hover:underline">{{ $ctx['application_number'] }}</a>
                                            @else
                                                <span class="font-mono">{{ $ctx['application_number'] }}</span>
                                            @endif
                                        </p>
                                    @endif
                                    @if ($ctx['loan_number'])
                                        <p>
                                            Loan
                                            @if ($ctx['loan_url'])
                                                <a href="{{ $ctx['loan_url'] }}" class="font-semibold text-brand hover:underline">{{ $ctx['loan_number'] }}</a>
                                            @else
                                                <span class="font-mono">{{ $ctx['loan_number'] }}</span>
                                            @endif
                                        </p>
                                    @endif
                                    @if ($ctx['asset'])
                                        <p>Asset · {{ $ctx['asset'] }}</p>
                                    @endif
                                    @if ($ctx['partner'])
                                        <p class="text-brand/80">
                                            Partner · {{ $ctx['partner'] }}
                                            @if ($ctx['partner_role'])
                                                <span class="text-gray-400">({{ $ctx['partner_role'] }})</span>
                                            @endif
                                        </p>
                                    @endif
                                    @if (! $ctx['product'] && ! $ctx['application_number'] && ! $ctx['loan_number'] && ! $ctx['partner'])
                                        <p class="text-gray-400">No linked loan / product</p>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-3">{{ $payment->methodShortLabel() }}</td>
                            <td class="px-5 py-3 font-medium whitespace-nowrap">{{ format_money($payment->amount) }}</td>
                            <td class="px-5 py-3">
                                @php
                                    $badge = match ($payment->status) {
                                        'verified', 'paid' => 'bg-emerald-50 text-emerald-700',
                                        'rejected' => 'bg-red-50 text-red-700',
                                        'clarification_requested' => 'bg-sky-50 text-sky-700',
                                        default => 'bg-amber-50 text-amber-800',
                                    };
                                @endphp
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $badge }}">
                                    {{ $payment->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.payments.show', $payment) }}"
                                   class="text-xs font-semibold text-brand hover:text-brand-light">
                                    {{ $needsBankMatch ? 'Match bank →' : 'Open →' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-gray-500">
                                @if ($status === 'awaiting_bank')
                                    No bank payments waiting for verification.
                                @elseif ($status === 'complete')
                                    No completed payments yet.
                                @else
                                    No payments in this view.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($payments->hasPages())
            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</x-admin.layout>
