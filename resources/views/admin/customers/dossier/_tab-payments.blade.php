@if ($dossier['payments']->isEmpty())
    <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-5 py-12 text-center">
        <p class="text-sm font-semibold text-gray-700">No payments on file</p>
        <p class="text-xs text-gray-500 mt-1">Registration, fees, and loan repayments will show here.</p>
    </div>
@else
    <div class="space-y-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Money movement</p>
            <h4 class="text-base font-bold text-gray-900 mt-0.5">Payments</h4>
            <p class="text-xs text-gray-500 mt-0.5">Amount and status are separate columns. Times are East Africa ({{ app_display_timezone() }}).</p>
        </div>

        <div class="overflow-x-auto rounded-2xl ring-1 ring-brand/10 bg-white shadow-sm">
            <table class="w-full table-fixed text-sm">
                <colgroup>
                    <col class="w-[18%]">
                    <col class="w-[18%]">
                    <col class="w-[18%]">
                    <col class="w-[16%]">
                    <col class="w-[16%]">
                    <col class="w-[14%]">
                </colgroup>
                <thead class="text-[10px] uppercase tracking-wider text-gray-500 bg-brand-muted/30 border-b border-gray-100">
                    <tr>
                        <th class="px-3 py-3 text-left font-semibold">When</th>
                        <th class="px-3 py-3 text-left font-semibold">Reference</th>
                        <th class="px-3 py-3 text-left font-semibold">Type</th>
                        <th class="px-3 py-3 text-right font-semibold">Amount</th>
                        <th class="px-3 py-3 text-left font-semibold">Status</th>
                        <th class="px-3 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($dossier['payments'] as $payment)
                        @php
                            $when = $payment->adminOccurredAt();
                            $status = (string) ($payment->status ?? '');
                            $statusTone = match (true) {
                                in_array($status, ['completed', 'success', 'successful', 'paid', 'verified'], true) => 'bg-emerald-100 text-emerald-800',
                                in_array($status, ['failed', 'cancelled', 'canceled', 'rejected'], true) => 'bg-red-100 text-red-800',
                                default => 'bg-amber-100 text-amber-900',
                            };
                        @endphp
                        <tr class="hover:bg-brand-muted/20 transition">
                            <td class="px-3 py-3.5 align-top">
                                <p class="font-semibold text-gray-900 leading-tight">{{ format_app_date($when) }}</p>
                                <p class="text-xs text-gray-500 tabular-nums mt-0.5">{{ format_app_datetime($when, 'H:i') }}</p>
                            </td>
                            <td class="px-3 py-3.5 align-top font-mono text-xs text-gray-700 break-all">{{ $payment->reference }}</td>
                            <td class="px-3 py-3.5 align-top text-gray-700 break-words">
                                {{ config("payment_types.types.{$payment->payment_type}.label", $payment->payment_type) }}
                            </td>
                            <td class="px-3 py-3.5 align-top text-right">
                                <p class="font-bold tabular-nums text-gray-900 whitespace-nowrap">{{ format_money((float) $payment->amount) }}</p>
                            </td>
                            <td class="px-3 py-3.5 align-top">
                                <span class="inline-flex max-w-full rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statusTone }}">
                                    {{ display_label($payment->status, 'payment_status') }}
                                </span>
                            </td>
                            <td class="px-3 py-3.5 align-top text-right whitespace-nowrap">
                                <a href="{{ route('admin.payments.show', $payment) }}"
                                   class="text-xs font-semibold text-brand hover:underline">
                                    Open →
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
