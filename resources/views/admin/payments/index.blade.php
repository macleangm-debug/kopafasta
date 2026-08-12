<x-admin.layout title="Verify payments" heading="" subheading="">

    <section class="mb-6">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-6 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Bank matching</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Verify payments</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Match borrower transfers to your bank or mobile-money account, then post to the ledger.
                </p>
            </div>
            <div class="bg-white px-6 py-5 grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}"
                   class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-4 hover:ring-amber-200 transition">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Pending</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['pending']) }}</p>
                </a>
                <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">Verified today</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['verified_today'] ?? 0) }}</p>
                </div>
                <a href="{{ route('admin.payments.index', ['status' => 'verified']) }}"
                   class="rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-4 hover:ring-sky-200 transition">
                    <p class="text-[10px] uppercase tracking-widest text-sky-800 font-semibold">Verified</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['verified']) }}</p>
                </a>
                <a href="{{ route('admin.payments.index', ['status' => 'rejected']) }}"
                   class="rounded-xl bg-rose-50 ring-1 ring-rose-100 px-4 py-4 hover:ring-rose-200 transition">
                    <p class="text-[10px] uppercase tracking-widest text-rose-800 font-semibold">Rejected</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['rejected']) }}</p>
                </a>
                <div class="rounded-xl {{ ($counts['missing_journal'] ?? 0) > 0 ? 'bg-rose-50 ring-rose-100' : 'bg-gray-50 ring-gray-100' }} ring-1 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest {{ ($counts['missing_journal'] ?? 0) > 0 ? 'text-rose-800' : 'text-gray-600' }} font-semibold">Missing journal</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['missing_journal'] ?? 0) }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="mb-4 flex flex-wrap gap-2 items-center">
        @foreach ([
            'pending' => $counts['pending'].' pending',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'all' => 'All',
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
        <a href="{{ route('admin.settings.payment-accounts') }}"
           class="ml-auto text-sm font-semibold text-brand hover:text-brand-light self-center">
            Payment account settings
        </a>
    </div>

    <form method="GET" action="{{ route('admin.payments.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="status" value="{{ $status }}">
        <div>
            <label class="block text-[11px] font-medium text-gray-500 mb-1">Payment type</label>
            <select name="type" class="rounded-lg border-gray-300 text-sm min-w-[12rem]" onchange="this.form.submit()">
                <option value="">All types</option>
                @foreach ($types as $key => $meta)
                    <option value="{{ $key }}" @selected(($type ?? '') === $key)>{{ $meta['label'] ?? $key }}</option>
                @endforeach
            </select>
        </div>
        @if ($type)
            <a href="{{ route('admin.payments.index', ['status' => $status]) }}" class="text-sm text-gray-500 hover:underline pb-2">Clear type</a>
        @endif
    </form>

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3">Reference</th>
                        <th class="px-5 py-3">Borrower</th>
                        <th class="px-5 py-3">Loan</th>
                        <th class="px-5 py-3">Payment type</th>
                        <th class="px-5 py-3">Method</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($payments as $payment)
                        @php
                            $customer = $payment->customer;
                            $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
                        @endphp
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-5 py-3 font-mono text-xs font-semibold">
                                <a href="{{ route('admin.payments.show', $payment) }}" class="text-brand hover:text-brand-light">
                                    {{ $payment->reference }}
                                </a>
                            </td>
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $name ?: '—' }}</div>
                                <div class="text-xs text-gray-500">{{ $customer->customer_number ?? '' }}</div>
                            </td>
                            <td class="px-5 py-3 font-mono text-xs">
                                @if ($payment->loan)
                                    <a href="{{ route('admin.loans.show', $payment->loan) }}" class="text-brand hover:text-brand-light">
                                        {{ $payment->loan->loan_number ?? $payment->loan->id }}
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">{{ $payment->typeLabel() }}</td>
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
                            <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">
                                {{ ($payment->payment_date ?? $payment->created_at)?->format('d-M-Y') }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.payments.show', $payment) }}"
                                   class="text-xs font-semibold text-brand hover:text-brand-light">
                                    Match bank →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-12 text-center text-gray-500">
                                No payments in this view.
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
