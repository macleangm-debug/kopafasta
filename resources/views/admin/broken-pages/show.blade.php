<x-admin.layout title="Broken page" heading="Exception incident" :back-url="route('admin.broken-pages.index')" back-label="Broken pages">
    <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 space-y-3 text-sm">
        <p><span class="text-gray-500">First seen</span> {{ ($brokenPage->first_seen_at ?? $brokenPage->created_at)?->format('d M Y H:i') }}</p>
        <p><span class="text-gray-500">Last seen</span> {{ ($brokenPage->last_seen_at ?? $brokenPage->created_at)?->format('d M Y H:i') }}</p>
        <p><span class="text-gray-500">Hits</span> {{ $brokenPage->occurrence_count ?? 1 }}</p>
        <p><span class="text-gray-500">HTTP</span> {{ $brokenPage->method }} {{ $brokenPage->status }}</p>
        <p><span class="text-gray-500">Category</span> {{ $brokenPage->category ?: 'unclassified' }}</p>
        @if ($brokenPage->classification_notes)
            <p><span class="text-gray-500">Classification</span> {{ $brokenPage->classification_notes }}</p>
        @endif
        <p class="font-mono text-xs break-all">{{ $brokenPage->path }}</p>
        <p class="text-xs break-all">
            <span class="text-gray-500">Referrer (untrusted / request-reported)</span>
            {{ $brokenPage->referrer ?: '— none on request —' }}
        </p>
        <p><span class="text-gray-500">Context</span> {{ $brokenPage->user?->name ?? 'Guest' }} · {{ $brokenPage->user_role ?: 'guest' }} · {{ $brokenPage->locale }}</p>
        <p class="text-xs text-gray-500">Staff-only exception type: {{ class_basename((string) $brokenPage->exception) }}</p>
        @if ($brokenPage->resolved_at)
            <p class="text-emerald-700 font-semibold">Resolved {{ $brokenPage->resolved_at->format('d M Y H:i') }} by {{ $brokenPage->resolver?->name }}</p>
            <p>{{ $brokenPage->resolution_notes }}</p>
        @else
            <form method="POST" action="{{ route('admin.broken-pages.resolve', $brokenPage) }}" class="space-y-3 pt-3">
                @csrf
                <label class="block text-xs font-semibold text-gray-500">Category</label>
                <select name="category" class="w-full rounded-lg border-gray-300 text-sm">
                    @foreach (['genuine_defect', 'broken_link', 'historical', 'expected_security', 'scanner_bot', 'invalid_request', 'duplicate'] as $category)
                        <option value="{{ $category }}" @selected(($brokenPage->category ?: 'genuine_defect') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
                <textarea name="resolution_notes" rows="3" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Root cause and what was fixed"></textarea>
                <button class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5">Mark resolved</button>
            </form>
        @endif
    </div>
</x-admin.layout>
