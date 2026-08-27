<x-admin.layout title="Broken page" heading="Exception incident" :back-url="route('admin.broken-pages.index')" back-label="Broken pages">
    <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 space-y-3 text-sm">
        <p><span class="text-gray-500">First seen</span> {{ ($brokenPage->first_seen_at ?? $brokenPage->created_at)?->format('d M Y H:i') }}</p>
        <p><span class="text-gray-500">Last seen</span> {{ ($brokenPage->last_seen_at ?? $brokenPage->created_at)?->format('d M Y H:i') }}</p>
        <p><span class="text-gray-500">Hits</span> {{ $brokenPage->occurrence_count ?? 1 }}</p>
        <p><span class="text-gray-500">HTTP</span> {{ $brokenPage->method }} {{ $brokenPage->status }}</p>
        <p class="font-mono text-xs break-all">{{ $brokenPage->path }}</p>
        @if ($brokenPage->referrer)
            <p class="text-xs break-all"><span class="text-gray-500">Referrer</span> {{ $brokenPage->referrer }}</p>
        @endif
        <p><span class="text-gray-500">Context</span> {{ $brokenPage->user?->name ?? 'Guest' }} · {{ $brokenPage->user_role ?: 'guest' }} · {{ $brokenPage->locale }}</p>
        <p class="text-xs text-gray-500">Staff-only exception type: {{ class_basename((string) $brokenPage->exception) }}</p>
        @if ($brokenPage->resolved_at)
            <p class="text-emerald-700 font-semibold">Resolved {{ $brokenPage->resolved_at->format('d M Y H:i') }} by {{ $brokenPage->resolver?->name }}</p>
            <p>{{ $brokenPage->resolution_notes }}</p>
        @else
            <form method="POST" action="{{ route('admin.broken-pages.resolve', $brokenPage) }}" class="space-y-3 pt-3">
                @csrf
                <textarea name="resolution_notes" rows="3" class="w-full rounded-lg border-gray-300 text-sm" placeholder="What was fixed?"></textarea>
                <button class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5">Mark resolved</button>
            </form>
        @endif
    </div>
</x-admin.layout>
