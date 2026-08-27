<x-admin.layout title="Broken pages" heading="Broken pages" subheading="Incident inventory for genuine 403/404/419/429/500/503 failures. Valid routes are not listed here.">
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('admin.broken-pages.index', ['status' => 'open']) }}" class="rounded-full px-3 py-1.5 text-xs font-semibold {{ ($status ?: 'open') === 'open' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200' }}">Open ({{ $openCount }})</a>
        <a href="{{ route('admin.broken-pages.index', ['status' => 'resolved']) }}" class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === 'resolved' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200' }}">Resolved</a>
        <a href="{{ route('admin.broken-pages.index', ['status' => 'all']) }}" class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === 'all' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200' }}">All</a>
    </div>

    <div class="hidden md:block rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="text-left px-4 py-3">Last seen</th>
                    <th class="text-left px-4 py-3">Status</th>
                    <th class="text-left px-4 py-3">Hits</th>
                    <th class="text-left px-4 py-3">Path</th>
                    <th class="text-left px-4 py-3">Role</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-3 text-gray-500">{{ ($row->last_seen_at ?? $row->created_at)?->format('d M H:i') }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $row->status }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ $row->occurrence_count ?? 1 }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row->path }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $row->user_role ?: 'guest' }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.broken-pages.show', $row) }}" class="text-brand font-semibold">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No exception incidents logged.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3">
        @forelse ($rows as $row)
            <a href="{{ route('admin.broken-pages.show', $row) }}" class="block rounded-2xl bg-white ring-1 ring-brand/10 p-4">
                <p class="text-xs text-gray-500">{{ ($row->last_seen_at ?? $row->created_at)?->format('d M H:i') }} · {{ $row->status }} · {{ $row->occurrence_count ?? 1 }} hits</p>
                <p class="font-mono text-sm mt-1">{{ $row->path }}</p>
            </a>
        @empty
            <p class="text-sm text-gray-500">No exception incidents logged.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $rows->links() }}</div>
</x-admin.layout>
