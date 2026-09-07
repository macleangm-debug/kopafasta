@props([
    'title',
    'selectedLabel',
    'desktopOpen' => 'desktopOpen',
    'sheetOpen' => 'pickerOpen',
    'sheetTitle' => null,
])

{{-- Trigger stays in the green card; desktop list teleports to body so overflow:hidden cannot clip it. --}}
<div class="relative mb-4"
     x-ref="plusPickerRoot"
     @keydown.escape.window="{{ $desktopOpen }} = false; {{ $sheetOpen }} = false"
     x-effect="
        if (! {{ $desktopOpen }}) return;
        $nextTick(() => {
            const root = $refs.plusPickerRoot;
            const panel = document.getElementById($id('plus-picker-panel'));
            if (! root || ! panel) return;
            const btn = root.querySelector('[data-plus-picker-trigger]');
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
            data-plus-picker-trigger
            class="w-full inline-flex items-center gap-3 rounded-xl bg-white/10 ring-1 ring-white/20 px-4 py-3 text-sm font-semibold text-white hover:bg-white/15"
            :aria-expanded="{{ $desktopOpen }} || {{ $sheetOpen }}"
            @click="window.matchMedia('(max-width: 1023px)').matches ? {{ $sheetOpen }} = true : {{ $desktopOpen }} = !{{ $desktopOpen }}">
        <span class="flex-1 text-left truncate">{{ $selectedLabel }}</span>
        <svg class="w-4 h-4 text-white/70 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 8l5 5 5-5z"/></svg>
    </button>

    <template x-teleport="body">
        <div class="hidden lg:contents">
            <div x-cloak
                 x-show="{{ $desktopOpen }}"
                 class="fixed inset-0 z-[10040]"
                 @click="{{ $desktopOpen }} = false"
                 aria-hidden="true"></div>
            <div x-cloak
                 x-show="{{ $desktopOpen }}"
                 x-transition.opacity
                 :id="$id('plus-picker-panel')"
                 role="listbox"
                 class="fixed z-[10041] overflow-y-auto overscroll-contain rounded-2xl border border-gray-200 bg-white shadow-2xl py-1 text-gray-900"
                 @click.outside="{{ $desktopOpen }} = false">
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
