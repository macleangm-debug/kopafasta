@props([
    'title',
    'selectedLabel',
    'desktopOpen' => 'desktopOpen',
    'sheetOpen' => 'pickerOpen',
    'sheetTitle' => null,
])

@php
    $panelId = 'plus-picker-'.substr(md5($title.'|'.$selectedLabel), 0, 10);
@endphp

{{-- Trigger stays in the green card; desktop list teleports to body so overflow:hidden cannot clip it. --}}
<div class="relative mb-4"
     x-ref="plusPickerRoot"
     @keydown.escape.window="
        if ({{ $desktopOpen }}) { {{ $desktopOpen }} = false; $refs.plusPickerTrigger?.focus(); }
        if ({{ $sheetOpen }}) { {{ $sheetOpen }} = false; $refs.plusPickerTrigger?.focus(); }
     "
     x-effect="
        if (! {{ $desktopOpen }}) return;
        $nextTick(() => {
            const root = $refs.plusPickerRoot;
            const panel = document.getElementById(@js($panelId));
            if (! root || ! panel) return;
            const btn = $refs.plusPickerTrigger;
            if (! btn) return;
            const rect = btn.getBoundingClientRect();
            const width = Math.min(Math.max(rect.width, 240), window.innerWidth - 24);
            let left = rect.left;
            if (left + width > window.innerWidth - 12) left = Math.max(12, window.innerWidth - width - 12);
            let top = rect.bottom + 6;
            const maxH = Math.min(22 * 16, window.innerHeight * 0.7);
            if (top + Math.min(maxH, 280) > window.innerHeight - 12) {
                top = Math.max(12, rect.top - 6 - Math.min(maxH, 280));
            }
            panel.style.top = top + 'px';
            panel.style.left = left + 'px';
            panel.style.width = width + 'px';
            panel.style.maxHeight = maxH + 'px';
        });
     ">
    <button type="button"
            x-ref="plusPickerTrigger"
            data-plus-picker-trigger
            class="w-full inline-flex items-center gap-3 rounded-xl bg-white/10 ring-1 ring-white/20 px-4 py-3 text-sm font-semibold text-white hover:bg-white/15"
            :aria-expanded="{{ $desktopOpen }} || {{ $sheetOpen }}"
            aria-haspopup="listbox"
            aria-controls="{{ $panelId }}"
            @click.stop="
                if (window.matchMedia('(max-width: 1023px)').matches) {
                    {{ $sheetOpen }} = true;
                } else {
                    {{ $desktopOpen }} = ! {{ $desktopOpen }};
                }
            "
            @keydown.arrow-down.prevent="
                if (window.matchMedia('(max-width: 1023px)').matches) {
                    {{ $sheetOpen }} = true;
                } else {
                    {{ $desktopOpen }} = true;
                    $nextTick(() => document.getElementById(@js($panelId))?.querySelector('a,button')?.focus());
                }
            ">
        <span class="flex-1 text-left truncate">{{ $selectedLabel }}</span>
        <svg class="w-4 h-4 text-white/70 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 8l5 5 5-5z"/></svg>
    </button>

    <template x-teleport="body">
        <div class="hidden lg:block" x-cloak x-show="{{ $desktopOpen }}">
            <div class="fixed inset-0 z-[10040]"
                 @click="{{ $desktopOpen }} = false; $refs.plusPickerTrigger?.focus()"
                 aria-hidden="true"></div>
            <div id="{{ $panelId }}"
                 role="listbox"
                 class="fixed z-[10041] overflow-y-auto overscroll-contain rounded-2xl border border-gray-200 bg-white shadow-2xl py-1 text-gray-900"
                 @keydown.escape.stop="{{ $desktopOpen }} = false; $refs.plusPickerTrigger?.focus()"
                 @keydown.arrow-down.prevent="
                    const items = [...$el.querySelectorAll('a,button')];
                    const i = items.indexOf(document.activeElement);
                    (items[i + 1] || items[0])?.focus();
                 "
                 @keydown.arrow-up.prevent="
                    const items = [...$el.querySelectorAll('a,button')];
                    const i = items.indexOf(document.activeElement);
                    (items[i - 1] || items[items.length - 1])?.focus();
                 "
                 @keydown.enter.prevent="document.activeElement?.click()">
                {{ $desktop }}
            </div>
        </div>
    </template>

    <x-site.bottom-sheet :title="$sheetTitle ?? $title" :open="$sheetOpen">
        <div class="space-y-1 max-h-[60vh] overflow-y-auto overscroll-contain">
            {{ $sheet }}
        </div>
    </x-site.bottom-sheet>
</div>
