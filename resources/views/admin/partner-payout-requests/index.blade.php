<x-admin.layout title="Partner payout requests" heading="Partner payout requests" subheading="Affiliate and recovery withdrawal requests — approve then mark paid when disbursed">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected'] as $key => $label)
            <a href="{{ route('admin.partner-payout-requests.index', ['status' => $key]) }}"
               @class([
                   'inline-flex rounded-lg px-3 py-1.5 text-xs font-semibold ring-1',
                   'bg-brand text-white ring-brand' => ($status ?? '') === $key,
                   'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50' => ($status ?? '') !== $key,
               ])>
                {{ $label }} ({{ $counts[$key] ?? 0 }})
            </a>
        @endforeach
        <a href="{{ route('admin.settings.affiliates') }}" class="ml-auto text-xs font-semibold text-brand hover:underline self-center">
            Affiliate settings (min payout / commissions) →
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Partner</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Notes</th>
                    <th class="px-4 py-3">Requested</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($requests as $row)
                    <tr class="align-top">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-900">{{ $row->partner?->name ?? '—' }}</p>
                            <p class="text-xs text-gray-500 font-mono">{{ $row->partner?->partner_number ?? $row->partner?->vendor_number }}</p>
                        </td>
                        <td class="px-4 py-3">{{ str_replace('_', ' ', $row->source_type ?? $row->wallet_type ?? '—') }}</td>
                        <td class="px-4 py-3 font-semibold tabular-nums">{{ format_money($row->amount) }}</td>
                        <td class="px-4 py-3">{{ ucfirst($row->status) }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600 max-w-xs">{{ $row->notes ?: '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $row->created_at?->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                @if ($row->status === 'pending')
                                    <form method="POST" action="{{ route('admin.partner-payout-requests.approve', $row) }}"
                                          @submit.prevent="window.confirmForm($el, {
                                              title: @js(__('admin.confirm.approve_payout_title')),
                                              message: @js(__('admin.confirm.approve_payout_message', ['amount' => format_money($row->amount), 'partner' => $row->partner?->name ?? '—'])),
                                              confirmLabel: @js(__('admin.confirm.approve_payout_confirm')),
                                              confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                                              tone: 'confirm',
                                          })">
                                        @csrf
                                        <button class="text-xs font-semibold text-emerald-700 hover:underline">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.partner-payout-requests.reject', $row) }}"
                                          @submit.prevent="window.confirmForm($el, {
                                              title: @js(__('admin.confirm.reject_payout_title')),
                                              message: @js(__('admin.confirm.reject_payout_message', ['amount' => format_money($row->amount), 'partner' => $row->partner?->name ?? '—'])),
                                              confirmLabel: @js(__('admin.confirm.reject_payout_confirm')),
                                              confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                                              tone: 'warning',
                                          })">
                                        @csrf
                                        <button class="text-xs font-semibold text-red-700 hover:underline">Reject</button>
                                    </form>
                                @endif
                                @if (in_array($row->status, ['pending', 'approved'], true))
                                    <form method="POST" action="{{ route('admin.partner-payout-requests.mark-paid', $row) }}"
                                          @submit.prevent="window.confirmForm($el, {
                                              title: @js(__('admin.confirm.mark_payout_paid_title')),
                                              message: @js(__('admin.confirm.mark_payout_paid_message', ['amount' => format_money($row->amount), 'partner' => $row->partner?->name ?? '—'])),
                                              confirmLabel: @js(__('admin.confirm.mark_payout_paid_confirm')),
                                              confirmClass: 'bg-brand hover:bg-brand-light text-white',
                                              tone: 'warning',
                                          })">
                                        @csrf
                                        <button class="text-xs font-semibold text-brand hover:underline">Mark paid</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">No payout requests in this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-admin.layout>
