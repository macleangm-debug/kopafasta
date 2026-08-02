<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="$record->code"
    :backUrl="route('admin.notification-templates.index')"
    :editUrl="route('admin.notification-templates.edit', $record)"
    :fields="[
        'Code'     => $record->code,
        'Name'     => $record->name,
        'Language' => strtoupper((string) ($record->locale ?? 'en')),
        'Channel'  => display_label($record->channel, 'channel') ?: strtoupper((string) $record->channel),
        'Subject'  => $record->subject ?? '—',
        'Status'   => $record->is_active ? 'Active' : 'Inactive',
    ]">
    <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-brand/10">
        <div class="px-6 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-800">Message content</h2>
            <a href="{{ route('admin.notification-templates.edit', $record) }}"
               class="text-xs font-semibold text-brand hover:underline">Edit content</a>
        </div>
        <div class="p-6 space-y-4">
            @if (filled($record->subject))
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Subject</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $record->subject }}</p>
                </div>
            @endif
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Body</p>
                <pre class="mt-2 whitespace-pre-wrap break-words rounded-xl bg-brand-muted/25 ring-1 ring-brand/5 px-4 py-3 text-sm text-gray-900 font-mono leading-relaxed">{{ $record->body }}</pre>
            </div>
        </div>
    </div>
</x-admin.show-page>
