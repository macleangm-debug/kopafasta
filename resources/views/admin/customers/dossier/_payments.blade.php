<div id="customer-payments" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-900">Payments</h3>
        <p class="text-xs text-gray-500 mt-0.5">Registration, application, post-approval, and loan repayments</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-2 text-left">Date</th>
                    <th class="px-5 py-2 text-left">Reference</th>
                    <th class="px-5 py-2 text-left">Type</th>
                    <th class="px-5 py-2 text-left">Amount</th>
                    <th class="px-5 py-2 text-left">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($dossier['payments'] as $payment)
                    @php $when = $payment->adminOccurredAt(); @endphp
                    <tr>
                        <td class="px-5 py-3 text-xs text-gray-600 whitespace-nowrap">
                            <p class="font-semibold text-gray-900">{{ format_app_date($when) }}</p>
                            <p class="tabular-nums text-gray-500 mt-0.5">{{ format_app_datetime($when, 'H:i') }}</p>
                        </td>
                        <td class="px-5 py-3 font-mono text-xs">
                            <a href="{{ route('admin.payments.show', $payment) }}" class="text-brand hover:text-brand-light">{{ $payment->reference }}</a>
                        </td>
                        <td class="px-5 py-3">{{ $payment->typeLabel() }}</td>
                        <td class="px-5 py-3 font-medium">{{ format_money($payment->amount) }}</td>
                        <td class="px-5 py-3">{{ $payment->statusLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">No payments recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
