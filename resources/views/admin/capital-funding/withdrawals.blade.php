<x-admin.layout
    :title="__('admin.capital_funding.withdrawals')"
    :heading="__('admin.capital_funding.withdrawals')"
    :subheading="__('admin.capital_partner.available_hint')"
    :back-url="route('admin.capital-funding.index')"
    back-label="Capital funding">

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        @if ($requests->isEmpty())
            <p class="p-6 text-sm text-gray-500">No withdrawal requests.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-200 bg-gray-50">
                        <tr>
                            <th class="text-left py-2 px-4">Partner</th>
                            <th class="text-right py-2 px-4">Amount</th>
                            <th class="text-left py-2 px-4">Status</th>
                            <th class="text-left py-2 px-4">Requested</th>
                            <th class="text-left py-2 px-4">Notes</th>
                            <th class="text-right py-2 px-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($requests as $req)
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4">
                                    <a href="{{ route('admin.lenders.show', $req->lender) }}" class="font-medium text-brand hover:underline">{{ $req->lender?->name }}</a>
                                </td>
                                <td class="py-2 px-4 text-right font-mono">{{ format_money($req->amount) }}</td>
                                <td class="py-2 px-4 capitalize text-xs">{{ $req->status }}</td>
                                <td class="py-2 px-4 text-xs text-gray-600">{{ $req->created_at?->format('d M Y H:i') }}</td>
                                <td class="py-2 px-4 text-xs text-gray-600 max-w-xs truncate">{{ $req->notes ?? '—' }}</td>
                                <td class="py-2 px-4 text-right">
                                    @if ($req->status === 'pending')
                                        <form method="post" action="{{ route('admin.capital-withdrawal-requests.approve', $req) }}" class="inline"
                                              @submit.prevent="window.confirmForm($el, {
                                                  title: @js(__('admin.confirm.capital_withdrawal_approve_title')),
                                                  message: @js(__('admin.confirm.capital_withdrawal_approve_message', ['amount' => format_money($req->amount), 'partner' => $req->lender?->name ?? '—'])),
                                                  confirmLabel: @js(__('admin.confirm.capital_withdrawal_approve_confirm')),
                                                  confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                                                  tone: 'confirm',
                                              })">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-emerald-700 hover:underline">{{ __('admin.capital_partner.approve_withdrawal') }}</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.capital-withdrawal-requests.reject', $req) }}" class="inline ml-2"
                                              @submit.prevent="window.confirmForm($el, {
                                                  title: @js(__('admin.confirm.capital_withdrawal_reject_title')),
                                                  message: @js(__('admin.confirm.capital_withdrawal_reject_message', ['amount' => format_money($req->amount), 'partner' => $req->lender?->name ?? '—'])),
                                                  confirmLabel: @js(__('admin.confirm.capital_withdrawal_reject_confirm')),
                                                  confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                                                  tone: 'warning',
                                              })">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">{{ __('admin.capital_partner.reject_withdrawal') }}</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-500">{{ $req->reviewed_at?->format('d M Y') ?? '—' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100">{{ $requests->links() }}</div>
        @endif
    </div>
</x-admin.layout>
