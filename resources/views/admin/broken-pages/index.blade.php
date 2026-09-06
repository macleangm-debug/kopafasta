<x-admin.layout title="Broken pages" heading="Broken pages" subheading="Needs Attention is the operational queue. Scanner/bot and expected security responses are classified and kept in history.">
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm px-4 py-3">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <a href="{{ route('admin.broken-pages.index', ['status' => 'needs_attention']) }}" class="rounded-full px-3 py-1.5 text-xs font-semibold {{ ($status ?: 'needs_attention') === 'needs_attention' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200' }}">Needs attention ({{ $needsAttentionCount }})</a>
        <a href="{{ route('admin.broken-pages.index', ['status' => 'open']) }}" class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === 'open' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200' }}">All open ({{ $openCount }})</a>
        <a href="{{ route('admin.broken-pages.index', ['status' => 'scanner']) }}" class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === 'scanner' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200' }}">Scanner history</a>
        <a href="{{ route('admin.broken-pages.index', ['status' => 'resolved']) }}" class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === 'resolved' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200' }}">Resolved</a>
        <a href="{{ route('admin.broken-pages.index', ['status' => 'all']) }}" class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === 'all' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200' }}">All</a>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-6 text-xs text-gray-600">
        <p>Baseline: {{ $baselineAt ? \Illuminate\Support\Carbon::parse($baselineAt)->format('d M Y H:i') : 'not set' }}</p>
        <form method="POST" action="{{ route('admin.broken-pages.classify-open') }}" class="inline">
            @csrf
            <button class="font-semibold text-brand hover:underline">Classify open</button>
        </form>
        <form method="POST" action="{{ route('admin.broken-pages.reset-baseline') }}" class="inline" onsubmit="return confirm('Reset the active monitoring baseline? History is kept.');">
            @csrf
            <button class="font-semibold text-brand hover:underline">Reset baseline</button>
        </form>
    </div>

    <div class="hidden md:block rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="text-left px-4 py-3">Last seen</th>
                    <th class="text-left px-4 py-3">HTTP</th>
                    <th class="text-left px-4 py-3">Category</th>
                    <th class="text-left px-4 py-3">Hits</th>
                    <th class="text-left px-4 py-3">Path</th>
                    <th class="text-left px-4 py-3">Role</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    <tr class="cursor-pointer hover:bg-gray-50" onclick="window.location='{{ route('admin.broken-pages.show', $row) }}'">
                        <td class="px-4 py-3 text-gray-500">{{ ($row->last_seen_at ?? $row->created_at)?->format('d M H:i') }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $row->status }}</td>
                        <td class="px-4 py-3 text-xs">{{ $row->category ?: 'unclassified' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ $row->occurrence_count ?? 1 }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row->path }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $row->user_role ?: 'guest' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No incidents in this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3">
        @forelse ($rows as $row)
            <a href="{{ route('admin.broken-pages.show', $row) }}" class="block rounded-2xl bg-white ring-1 ring-brand/10 p-4">
                <p class="text-xs text-gray-500">{{ ($row->last_seen_at ?? $row->created_at)?->format('d M H:i') }} · {{ $row->status }} · {{ $row->category ?: 'unclassified' }} · {{ $row->occurrence_count ?? 1 }} hits</p>
                <p class="font-mono text-sm mt-1">{{ $row->path }}</p>
            </a>
        @empty
            <p class="text-sm text-gray-500">No incidents in this view.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $rows->links() }}</div>
</x-admin.layout>
