@php
    $name = $contact['name'] ?? $participant['name'] ?? $participant['label'] ?? '—';
    $index = (int) ($participant['index'] ?? 1);
    $total = (int) ($participant['total'] ?? 1);
    $phone = $contact['phone'] ?? null;
    $nationalId = $contact['national_id'] ?? $contact['detail'] ?? null;
    $profileHref = $contact['profile_href'] ?? ($step['destination']['profile_href'] ?? null);
    $idUrl = $idEvidenceUrl ?? null;
    $actionClass = 'inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-bold text-brand ring-1 ring-brand/25 hover:bg-brand-muted/40';
@endphp
<div class="rounded-2xl bg-slate-50 ring-1 ring-brand/10 px-4 py-3">
    <div class="min-w-0">
        <p class="text-base font-bold text-slate-900 break-words">{{ $name }}</p>
        @if ($total > 1)
            <p class="text-xs font-semibold text-slate-600 mt-0.5">Member {{ $index }} of {{ $total }}</p>
        @endif
        <dl class="mt-2 space-y-0.5 text-xs text-slate-700">
            <div class="flex gap-2">
                <dt class="text-slate-500">Phone</dt>
                <dd class="font-semibold">{{ $phone ?: '—' }}</dd>
            </div>
            <div class="flex gap-2">
                <dt class="text-slate-500">National ID</dt>
                <dd class="font-semibold {{ ! empty($nationalIdMissing) || ! filled($nationalId) ? 'text-amber-800' : '' }}">
                    {{ filled($nationalId) ? $nationalId : 'Missing' }}
                </dd>
            </div>
        </dl>
    </div>
    <div class="mt-3 flex flex-wrap gap-2">
        @if ($phone)
            <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="{{ $actionClass }}">
                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 3.5A1.5 1.5 0 0 1 3.5 2h1.4c.7 0 1.3.5 1.4 1.2l.4 2.3c.1.5-.1 1-.5 1.3L5.1 8c.8 1.7 2.2 3.1 3.9 3.9l1.2-1.1c.3-.4.8-.6 1.3-.5l2.3.4c.7.1 1.2.7 1.2 1.4v1.4A1.5 1.5 0 0 1 13.5 15C7.7 15 3 10.3 3 4.5 3 4 2.6 3.5 2 3.5Z"/></svg>
                Call
            </a>
        @endif
        @if ($profileHref)
            <a href="{{ guided_evidence_url($profileHref, 'guided', $evidenceReturn ?? []) }}" class="{{ $actionClass }}">
                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-7 9a7 7 0 1 1 14 0H3Z" clip-rule="evenodd"/></svg>
                View profile
            </a>
        @endif
        @if ($idUrl)
            <button type="button"
                    onclick="window.kfOpenDocumentPreview(@js($idUrl), 'National ID', 'image')"
                    class="{{ $actionClass }}">
                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8.4a2 2 0 0 0-.6-1.4l-2.4-2.4A2 2 0 0 0 13.6 4H4Zm8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/></svg>
                View National ID
            </button>
        @endif
    </div>
</div>
