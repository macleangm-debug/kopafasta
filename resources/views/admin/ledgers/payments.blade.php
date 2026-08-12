<x-admin.layout
    title="Money ledger"
    heading=""
    subheading="">

    <section class="mb-6">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-6 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Finance</p>
                <h1 class="text-2xl font-bold mt-1">Money ledger</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Incoming borrower money and outgoing payouts in one place — fees, membership, repayments, partner settlements, and disbursements.
                </p>
                <div class="mt-4 inline-flex rounded-xl bg-white/10 p-1 ring-1 ring-white/20">
                    <a href="{{ route('admin.payments.ledger', ['direction' => 'in', 'tab' => 'all']) }}"
                       class="px-4 py-2 rounded-lg text-sm font-semibold {{ $direction === 'in' ? 'bg-brand-gold text-brand' : 'text-white/85 hover:bg-white/10' }}">
                        Incoming complete
                        <span class="ml-1 tabular-nums opacity-80">({{ $counts['in_count'] }})</span>
                    </a>
                    <a href="{{ route('admin.payments.ledger', ['direction' => 'out', 'tab' => 'partners']) }}"
                       class="px-4 py-2 rounded-lg text-sm font-semibold {{ $direction === 'out' ? 'bg-brand-gold text-brand' : 'text-white/85 hover:bg-white/10' }}">
                        Outgoing complete
                        <span class="ml-1 tabular-nums opacity-80">({{ $counts['out_count'] }})</span>
                    </a>
                </div>
            </div>
            <div class="bg-white px-6 py-4 grid sm:grid-cols-4 gap-3">
                @if ($direction === 'in')
                    <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-100 px-4 py-3">
                        <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">Incoming · complete</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ format_money((float) $counts['in_amount']) }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 tabular-nums">{{ number_format($counts['in_count']) }} payments</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-3">
                        <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Awaiting bank</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $counts['pending'] }}</p>
                    </div>
                    <div class="rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-3">
                        <p class="text-[10px] uppercase tracking-widest text-sky-800 font-semibold">Membership queue</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $counts['membership_pending'] }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-100 px-4 py-3">
                        <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">Repayments pending</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $counts['repayments_pending'] }}</p>
                    </div>
                @else
                    <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Outgoing · complete</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ format_money((float) $counts['out_amount']) }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 tabular-nums">{{ number_format($counts['out_count']) }} payouts</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-3">
                        <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Partner payouts pending</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $counts['partners_pending'] }}</p>
                    </div>
                    <div class="rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-3">
                        <p class="text-[10px] uppercase tracking-widest text-sky-800 font-semibold">Capital withdrawals pending</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $counts['capital_pending'] }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-100 px-4 py-3">
                        <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">Disbursements</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $counts['disbursements'] }}</p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if ($direction === 'in')
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ([
                'all' => 'All payments',
                'fees' => 'Fees',
                'repayments' => 'Loan repayments',
                'membership' => 'Membership',
                'repayment_queue' => 'Repayment queue',
            ] as $key => $label)
                <a href="{{ route('admin.payments.ledger', array_filter(['direction' => 'in', 'tab' => $key, 'status' => $status !== 'all' ? $status : null])) }}"
                   class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $tab === $key ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                    {{ $label }}
                    @if ($key === 'membership')
                        <span class="text-xs opacity-70">({{ $counts['membership_pending'] }})</span>
                    @elseif ($key === 'repayment_queue')
                        <span class="text-xs opacity-70">({{ $counts['repayments_pending'] }})</span>
                    @endif
                </a>
            @endforeach
        </div>

        @if (in_array($tab, ['all', 'fees', 'repayments'], true))
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach (['verified' => 'Complete', 'pending' => 'Awaiting bank', 'rejected' => 'Rejected', 'all' => 'Any status'] as $key => $label)
                    <a href="{{ route('admin.payments.ledger', array_filter(['direction' => 'in', 'tab' => $tab !== 'all' ? $tab : null, 'status' => $key, 'type' => $type ?: null])) }}"
                       class="px-2.5 py-1 rounded-md text-xs font-semibold {{ $status === $key ? 'bg-gray-900 text-white' : 'bg-white text-gray-500 ring-1 ring-gray-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.payments.ledger') }}" class="mb-4 flex flex-wrap items-end gap-3">
                <input type="hidden" name="direction" value="in">
                <input type="hidden" name="tab" value="{{ $tab }}">
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
            </form>

            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-5 py-3">Date</th>
                                <th class="px-5 py-3">Reference</th>
                                <th class="px-5 py-3">Borrower</th>
                                <th class="px-5 py-3">Type</th>
                                <th class="px-5 py-3">Amount</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Journal</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($payments as $payment)
                                @php
                                    $customer = $payment->customer;
                                    $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
                                    $when = $payment->adminOccurredAt();
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 text-xs text-gray-600 whitespace-nowrap">
                                        <p class="font-semibold text-gray-900">{{ format_app_date($when) }}</p>
                                        <p class="tabular-nums text-gray-500 mt-0.5">{{ format_app_datetime($when, 'H:i') }}</p>
                                    </td>
                                    <td class="px-5 py-3 font-mono text-xs font-semibold">
                                        <a href="{{ route('admin.payments.show', $payment) }}" class="text-brand hover:text-brand-light">{{ $payment->reference }}</a>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="font-medium">{{ $name ?: '—' }}</div>
                                        @if ($customer)
                                            <a href="{{ route('admin.customers.show', $customer) }}" class="text-xs text-gray-500 hover:text-brand">Profile →</a>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">{{ config("payment_types.types.{$payment->payment_type}.label", $payment->payment_type) }}</td>
                                    <td class="px-5 py-3 font-semibold tabular-nums">{{ format_money((float) $payment->amount) }}</td>
                                    <td class="px-5 py-3">
                                        <x-admin.badge :value="$payment->status" group="payment_status" />
                                    </td>
                                    <td class="px-5 py-3 text-xs text-gray-500">
                                        @if ($payment->journalEntry)
                                            <a href="{{ route('admin.journal-entries.show', $payment->journalEntry) }}" class="text-brand hover:underline">
                                                {{ $payment->journalEntry->entry_number ?? '#'.$payment->journalEntry->id }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('admin.payments.show', $payment) }}" class="text-xs font-semibold text-brand hover:text-brand-light">Open →</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-5 py-12 text-center text-gray-500">No payments in this filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 border-t border-gray-100">{{ $payments->links() }}</div>
            </div>
        @elseif ($tab === 'membership')
            <div class="mb-4 flex items-center justify-between gap-3">
                <p class="text-sm text-gray-600">
                    Borrowers who pay by bank transfer appear here until an admin verifies the transfer.
                </p>
                <a href="{{ route('admin.settings.membership') }}"
                   class="text-sm font-semibold text-brand hover:text-brand-light">
                    Membership settings
                </a>
            </div>
            @include('admin.ledgers._membership_queue')
        @elseif ($tab === 'repayment_queue')
            @livewire('admin.repayments-table')
        @endif
    @else
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ([
                'partners' => 'Partners & suppliers',
                'capital' => 'Capital partners',
                'settlements' => 'Settlement batches',
                'disbursements' => 'Disbursements',
            ] as $key => $label)
                <a href="{{ route('admin.payments.ledger', array_filter(['direction' => 'out', 'tab' => $key, 'status' => $status ?: null])) }}"
                   class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $tab === $key ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if ($tab === 'partners')
            @include('admin.ledgers._payout_partners')
        @elseif ($tab === 'capital')
            @include('admin.ledgers._payout_capital')
        @elseif ($tab === 'settlements')
            @include('admin.ledgers._payout_settlements')
        @elseif ($tab === 'disbursements')
            @include('admin.ledgers._payout_disbursements')
        @endif
    @endif
</x-admin.layout>
