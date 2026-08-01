<div>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3 text-left">Code</th>
                    <th class="px-5 py-3 text-left">Name</th>
                    <th class="px-5 py-3 text-left">Type</th>
                    <th class="px-5 py-3 text-left">Status</th>
                    <th class="px-5 py-3 text-left">Period</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono text-xs">{{ $row->code }}</td>
                        <td class="px-5 py-3 font-medium">{{ $row->name }}</td>
                        <td class="px-5 py-3">{{ str_replace('_', ' ', $row->type) }}</td>
                        <td class="px-5 py-3 capitalize">{{ $row->status }}</td>
                        <td class="px-5 py-3 text-xs text-gray-500">
                            {{ optional($row->starts_at)->format('d M Y') ?? '—' }}
                            →
                            {{ optional($row->ends_at)->format('d M Y') ?? '—' }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.promotions.show', $row) }}" class="text-brand hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No campaigns yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</div>
