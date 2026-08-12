<div class="mb-4">
    <a href="{{ route('admin.partner-settlements.index') }}" class="text-xs font-semibold text-gray-500 hover:text-brand">Settlement batches →</a>
</div>
<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Batch</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($settlements as $batch)
                <tr>
                    <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                        <p class="font-semibold text-gray-900">{{ format_app_date($batch->created_at) }}</p>
                        <p class="tabular-nums text-gray-500 mt-0.5">{{ format_app_datetime($batch->created_at, 'H:i') }}</p>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $batch->reference ?? '#'.$batch->id }}</td>
                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($batch->status ?? '—')) }}</td>
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
