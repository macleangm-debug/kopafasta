<x-admin.layout title="Partner settlements" heading="Partner settlement batches" subheading="Weekly payout batches for suppliers, GPS installers, and affiliates">
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.partner-payments.index') }}" class="text-sm font-medium text-brand hover:text-brand-light">Review pending payments →</a>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Partner</th>
                    <th class="px-4 py-3">Period</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($settlements as $settlement)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $settlement->reference }}</td>
                        <td class="px-4 py-3">{{ $settlement->vendor?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ optional($settlement->period_start)->format('d M Y') }} – {{ optional($settlement->period_end)->format('d M Y') }}
                        </td>
                        <td class="px-4 py-3">{{ format_money($settlement->total_amount) }}</td>
                        <td class="px-4 py-3">{{ ucfirst($settlement->status) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.partner-settlements.show', $settlement) }}" class="text-brand hover:text-brand-light">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No settlement batches yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $settlements->links() }}</div>
</x-admin.layout>
