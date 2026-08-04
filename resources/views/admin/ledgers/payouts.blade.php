<x-admin.layout
    title="Payout ledger"
    heading=""
    subheading="">

    <section class="mb-6">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-6 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Outbound money</p>
                <h1 class="text-2xl font-bold mt-1">Payout ledger</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Partner, supplier, affiliate, and capital-partner payouts — approve, batch, and track withdrawals separately from borrower payments.
                </p>
            </div>
            <div class="bg-white px-6 py-4 grid sm:grid-cols-3 gap-3">
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Partner payouts pending</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $counts['partners_pending'] }}</p>
                </div>
                <div class="rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-sky-800 font-semibold">Capital withdrawals pending</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $counts['capital_pending'] }}</p>
                </div>
                <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Settlement batches</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $counts['settlements'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ([
            'partners' => 'Partners & suppliers',
            'capital' => 'Capital partners',
            'settlements' => 'Settlement batches',
        ] as $key => $label)
            <a href="{{ route('admin.payouts.ledger', array_filter(['tab' => $key, 'status' => $status ?: null])) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $tab === $key ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
        <a href="{{ route('admin.payments.ledger') }}"
           class="ml-auto px-3 py-1.5 rounded-lg text-sm font-semibold text-brand bg-white ring-1 ring-brand/20 hover:bg-brand-muted/30">
            ← Payments ledger
        </a>
    </div>

    @if ($tab === 'partners')
        <div class="mb-4 flex flex-wrap gap-2 text-sm">
            <a href="{{ route('admin.payouts.ledger', ['tab' => 'partners']) }}" @class(['font-medium', 'text-amber-700' => $status === '', 'text-gray-500' => $status !== ''])>All</a>
            @foreach ($statuses as $item)
                <a href="{{ route('admin.payouts.ledger', ['tab' => 'partners', 'status' => $item]) }}" @class(['font-medium capitalize', 'text-amber-700' => $status === $item, 'text-gray-500' => $status !== $item])>{{ $item }}</a>
            @endforeach
            <a href="{{ route('admin.partner-payments.index') }}" class="ml-auto text-xs font-semibold text-gray-500 hover:text-brand">Classic queue →</a>
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Partner</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($partnerPayments as $payment)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $payment->invoice_number }}</td>
                            <td class="px-4 py-3">{{ $payment->partner?->name ?? $payment->vendor?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $payment->description ?? str_replace('_', ' ', $payment->source_type ?? '—') }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ format_money((float) $payment->amount) }}</td>
                            <td class="px-4 py-3">{{ ucfirst($payment->status) }}</td>
                            <td class="px-4 py-3 text-right space-x-2">
                                @if ($payment->status === 'pending')
                                    <form method="post" action="{{ route('admin.partner-payments.approve', $payment) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-emerald-700 hover:text-emerald-900">Approve</button>
                                    </form>
                                    <form method="post" action="{{ route('admin.partner-payments.cancel', $payment) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-red-700 hover:text-red-900">Cancel</button>
                                    </form>
                                @elseif ($payment->partnerSettlement)
                                    <a href="{{ route('admin.partner-settlements.show', $payment->partnerSettlement) }}" class="text-amber-700">Batch</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No partner payouts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $partnerPayments->links() }}</div>

    @elseif ($tab === 'capital')
        <div class="mb-4">
            <a href="{{ route('admin.capital-funding.withdrawals') }}" class="text-xs font-semibold text-gray-500 hover:text-brand">Capital withdrawal workspace →</a>
        </div>
        <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Capital partner</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Requested</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($capitalWithdrawals as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $row->lender?->name ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ format_money((float) $row->amount) }}</td>
                            <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) $row->status) }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $row->created_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.capital-funding.withdrawals') }}" class="text-xs font-semibold text-brand">Review →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No capital withdrawal requests.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $capitalWithdrawals->links() }}</div>

    @else
        <div class="mb-4">
            <a href="{{ route('admin.partner-settlements.index') }}" class="text-xs font-semibold text-gray-500 hover:text-brand">Settlement batches →</a>
        </div>
        <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Batch</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($settlements as $batch)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs">{{ $batch->reference ?? '#'.$batch->id }}</td>
                            <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($batch->status ?? '—')) }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $batch->created_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.partner-settlements.show', $batch) }}" class="text-xs font-semibold text-brand">Open →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-gray-500">No settlement batches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $settlements->links() }}</div>
    @endif
</x-admin.layout>
