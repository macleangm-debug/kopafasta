<div class="space-y-5">
    <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900">
        <p class="font-semibold">Browse by when the message is sent</p>
        <p class="text-xs text-sky-800 mt-1">Pick a stage (registration, application, repayment, late payment…), then edit English and Kiswahili copy for that event. Turning events on/off is under Settings → Transactional messaging.</p>
    </div>

    <div class="flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="relative flex-1">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search by name, code, or message text…"
                   class="w-full rounded-xl border border-brand/15 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand focus:ring-2 focus:ring-brand/15">
        </div>
        <p class="text-xs text-gray-500 shrink-0">{{ $totalEvents }} event{{ $totalEvents === 1 ? '' : 's' }} shown</p>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="$set('lifecycle', '')"
                @class([
                    'rounded-full px-3 py-1.5 text-xs font-semibold ring-1 transition',
                    'bg-brand text-white ring-brand' => $lifecycle === '',
                    'bg-white text-gray-700 ring-gray-200 hover:ring-brand/40' => $lifecycle !== '',
                ])>
            All stages
        </button>
        @foreach ($lifecycles as $key => $meta)
            @continue($key === 'other' && ($stageCounts[$key] ?? 0) === 0)
            <button type="button" wire:click="setLifecycle(@js($key))"
                    @class([
                        'rounded-full px-3 py-1.5 text-xs font-semibold ring-1 transition',
                        'bg-brand text-white ring-brand' => $lifecycle === $key,
                        'bg-white text-gray-700 ring-gray-200 hover:ring-brand/40' => $lifecycle !== $key,
                    ])>
                {{ $meta['label'] }}
                <span class="opacity-70">({{ $stageCounts[$key] ?? 0 }})</span>
            </button>
        @endforeach
    </div>

    @forelse ($sections as $stageKey => $section)
        <section class="bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-muted/40 to-white">
                <h2 class="text-sm font-bold text-gray-900">{{ $section['label'] }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ $section['hint'] }}</p>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($section['events'] as $code => $rows)
                    @php
                        $event = $catalog[$code] ?? null;
                        $byLocale = $rows->keyBy(fn ($r) => $r->locale ?: 'en');
                        $en = $byLocale->get('en');
                        $sw = $byLocale->get('sw');
                        $primary = $en ?? $sw ?? $rows->first();
                        $title = $event['name'] ?? $primary->name;
                        $desc = $event['description'] ?? null;
                    @endphp
                    <div class="px-5 py-4 flex flex-col lg:flex-row lg:items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-900">{{ $title }}</h3>
                                <span class="font-mono text-[10px] text-gray-400 bg-gray-50 ring-1 ring-gray-200 rounded px-1.5 py-0.5">{{ $code }}</span>
                                @if (! empty($event['critical']))
                                    <span class="text-[10px] uppercase tracking-wide font-bold text-rose-700 bg-rose-50 ring-1 ring-rose-200 rounded px-1.5 py-0.5">Critical</span>
                                @endif
                            </div>
                            @if ($desc)
                                <p class="text-xs text-gray-500 mt-1">{{ $desc }}</p>
                            @endif
                            <p class="text-xs text-gray-600 mt-2 line-clamp-2 font-mono">{{ \Illuminate\Support\Str::limit($primary->body, 140) }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <a href="{{ route('admin.notification-templates.edit', $primary) }}"
                               class="inline-flex items-center gap-1.5 rounded-xl bg-brand-gold text-brand px-3 py-2 text-xs font-semibold hover:brightness-95">
                                Edit EN + SW
                            </a>
                            <a href="{{ route('admin.notification-templates.show', $primary) }}"
                               class="inline-flex items-center rounded-xl px-2.5 py-2 text-xs font-semibold text-gray-600 hover:text-brand">
                                View
                            </a>
                            <div class="flex gap-1 text-[10px] font-semibold uppercase tracking-wide">
                                @foreach (config('notification_templates.locales', ['en' => 'English', 'sw' => 'Kiswahili']) as $locale => $label)
                                    @if ($byLocale->has($locale))
                                        <span class="rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 px-2 py-0.5">{{ $locale }}</span>
                                    @else
                                        <span class="rounded-full bg-amber-50 text-amber-800 ring-1 ring-amber-200 px-2 py-0.5">{{ $locale }} missing</span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-6 py-16 text-center text-sm text-gray-500">
            No templates match this filter.
            <a href="{{ route('admin.notification-templates.create') }}" class="block mt-3 font-semibold text-brand hover:underline">Create a template</a>
        </div>
    @endforelse
</div>
