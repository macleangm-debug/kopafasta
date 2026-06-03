<x-site.vendor-layout title="Invoice" active="payments">

    <div class="mb-5 flex items-center justify-between">
        <a href="{{ route('site.vendor.payments') }}" class="text-sm text-indigo-600 hover:underline">← Back to payments</a>
        <button onclick="window.print()" class="text-sm rounded-lg border border-gray-300 px-3 py-1.5 hover:bg-gray-50">Print / Save PDF</button>
    </div>

    <div class="max-w-3xl mx-auto rounded-2xl border border-gray-200 bg-white p-8 print:border-0 print:p-0">
        <div class="flex items-start justify-between mb-8">
            <div>
                <p class="text-xs text-gray-500 uppercase">Invoice</p>
                <h1 class="font-extrabold text-2xl font-mono">{{ $payment->invoice_number }}</h1>
            </div>
            <div class="text-right">
                <p class="font-extrabold text-lg">Kopafasta</p>
                <p class="text-xs text-gray-500">Vendor settlement</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-6 mb-8">
            <div>
                <p class="text-xs text-gray-500 uppercase mb-1">Vendor</p>
                <p class="font-semibold">{{ $vendor->name }}</p>
                <p class="text-sm text-gray-600">{{ $vendor->vendor_number }}</p>
                <p class="text-sm text-gray-600">{{ $vendor->phone }} · {{ $vendor->email }}</p>
            </div>
            <div class="sm:text-right">
                <p class="text-xs text-gray-500 uppercase mb-1">Issued</p>
                <p class="font-semibold">{{ $payment->created_at->format('d M Y') }}</p>
                @php $pc = $payment->status === 'paid' ? 'emerald' : 'amber'; @endphp
                <p class="mt-1 inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $pc }}-100 text-{{ $pc }}-700">{{ $payment->status }}</p>
            </div>
        </div>

        <table class="w-full text-sm mb-6">
            <thead class="border-b border-gray-200 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="text-left py-2">Description</th>
                    <th class="text-right py-2">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-gray-100">
                    <td class="py-3">
                        {{ $payment->task ? ucfirst(str_replace('_',' ', $payment->task->task_type)) : 'Vendor service' }}
                        @if ($payment->task)<div class="text-xs text-gray-500">Task #{{ $payment->task->id }} · {{ $payment->task->customer_name }}</div>@endif
                    </td>
                    <td class="py-3 text-right font-semibold">{{ $fmt($payment->amount) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td class="pt-4 text-right text-sm text-gray-500">Total</td>
                    <td class="pt-4 text-right text-xl font-extrabold">{{ $fmt($payment->amount) }}</td>
                </tr>
            </tfoot>
        </table>

        @if ($payment->paid_at)
            <p class="text-sm text-emerald-700">Paid on {{ $payment->paid_at->format('d M Y') }} · ref <span class="font-mono">{{ $payment->reference }}</span></p>
        @else
            <p class="text-sm text-gray-500">Settlement is processed by the finance team. You will be notified when payment is sent.</p>
        @endif
    </div>
</x-site.vendor-layout>
