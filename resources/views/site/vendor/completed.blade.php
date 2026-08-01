<x-site.vendor-layout title="Completed jobs" active="completed">
    <h1 class="text-2xl font-extrabold mb-1">Completed jobs</h1>
    <p class="text-sm text-gray-500 mb-5">Past jobs you finished.</p>

    @if ($tasks->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">No completed jobs yet.</div>
    @else
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-3">Task</th>
                        <th class="text-left px-4 py-3">Customer</th>
                        <th class="text-left px-4 py-3">Completed</th>
                        <th class="text-left px-4 py-3">Fee</th>
                        <th class="text-left px-4 py-3">Payment</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($tasks as $t)
                        @php $pc = $t->payment_status === 'paid' ? 'emerald' : 'amber'; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold">{{ ucfirst(str_replace('_',' ', $t->task_type)) }}</td>
                            <td class="px-4 py-3">{{ $t->customer_name ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $t->completed_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3">{{ format_money($t->fee_amount) }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $pc }}-100 text-{{ $pc }}-700">{{ $t->payment_status }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('site.partner.task', $t) }}" class="text-brand hover:underline text-sm font-semibold">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $tasks->links() }}</div>
    @endif
</x-site.vendor-layout>
