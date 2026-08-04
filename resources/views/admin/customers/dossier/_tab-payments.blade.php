@if ($dossier['payments']->isEmpty())
    <p class="text-sm text-gray-500">No payments on file for this member.</p>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-xs uppercase text-gray-500 border-b border-gray-100">
                <tr>
                    <th class="py-2 text-left">Reference</th>
                    <th class="py-2 text-left">Type</th>
                    <th class="py-2 text-right">Amount</th>
                    <th class="py-2 text-left">Status</th>
                    <th class="py-2 text-left">Date</th>
                    <th class="py-2 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($dossier['payments'] as $payment)
                    <tr>
                        <td class="py-3 font-mono text-xs">{{ $payment->reference }}</td>
                        <td class="py-3">{{ config("payment_types.types.{$payment->payment_type}.label", $payment->payment_type) }}</td>
                        <td class="py-3 text-right tabular-nums">{{ format_money((float) $payment->amount) }}</td>
                        <td class="py-3">{{ display_label($payment->status, 'payment_status') }}</td>
                        <td class="py-3 text-gray-500">{{ $payment->created_at?->format('d M Y') }}</td>
                        <td class="py-3 text-right">
                            <a href="{{ route('admin.payments.show', $payment) }}" class="text-xs font-semibold text-brand">Open →</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
