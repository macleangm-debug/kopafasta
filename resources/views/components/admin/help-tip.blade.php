@props(['text' => ''])

<span x-data="{ open: false }" class="relative inline-flex align-middle ml-0.5">
    <button type="button"
            class="inline-flex size-4 items-center justify-center rounded-full text-[10px] font-bold leading-none text-brand ring-1 ring-brand/30 hover:bg-brand-muted"
            @click.prevent="open = !open"
            aria-label="Help">
        ⓘ
    </button>
    <span x-show="open" x-cloak @click.outside="open = false"
          class="absolute z-30 left-0 top-5 w-64 rounded-xl bg-white p-3 text-xs leading-relaxed text-gray-600 shadow-lg ring-1 ring-gray-200">
        {{ $text }}{{ $slot }}
    </span>
</span>
