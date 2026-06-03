<x-site.vendor-layout title="Payments" active="payments">

    <h1 class="text-2xl font-extrabold mb-1">Payments & earnings</h1>
    <p class="text-sm text-gray-500 mb-5">Invoices auto-generate when you complete a paid task.</p>

    <div class="grid sm:grid-cols-3 gap-3 mb-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-xs text-gray-500 uppercase">Total earned</p>
            <p class="text-2xl font-extrabold text-emerald-700 mt-1">{{ $fmt($totals['paid']) }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-xs text-gray-500 uppercase">Pending settlement</p>
            <p class="text-2xl font-extrabold text-amber-700 mt-1">{{ $fmt($totals['pending']) }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-xs text-gray-500 uppercase">Total invoices</p>
            <p class="text-2xl font-extrabold mt-1">{{ $totals['count'] }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="text-left px-4 py-3">Invoice</th>
                    <th class="text-left px-4 py-3">Task</th>
                    <th class="text-left px-4 py-3">Amount</th>
                    <th class="text-left px-4 py-3">Status</th>
                    <th class="text-left px-4 py-3">Paid</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($payments as $p)
                    @php $pc = $p->status === 'paid' ? 'emerald' : ($p->status === 'cancelled' ? 'gray' : 'amber'); @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $p->invoice_number }}</td>
                        <td class="px-4 py-3">{{ $p->task ? ucfirst(str_replace('_',' ', $p->task->task_type)) : '—' }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $fmt($p->amount) }}</td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $pc }}-100 text-{{ $pc }}-700">{{ $p->status }}</span></td>
                        <td class="px-4 py-3 text-gray-600">{{ $p->paid_at?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('site.vendor.invoice', $p) }}" class="text-indigo-600 hover:underline text-sm font-semibold">Invoice</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500 text-sm">No invoices yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $payments->links() }}</div>
</x-site.vendor-layout>
