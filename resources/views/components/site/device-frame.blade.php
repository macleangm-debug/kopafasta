@props([
    'caption' => null,
])

<div class="relative mx-auto w-[min(100%,280px)]">
    <div class="rounded-[2.4rem] bg-gray-950 p-2.5 shadow-2xl ring-1 ring-black/20">
        <div class="rounded-[1.9rem] overflow-hidden bg-brand aspect-[9/19] relative">
            <div class="absolute top-0 inset-x-0 h-6 bg-black/20 z-10"></div>
            <div class="h-full overflow-hidden">
                {{ $slot }}
            </div>
        </div>
    </div>
    @if ($caption)
        <p class="mt-3 text-center text-xs text-gray-500">{{ $caption }}</p>
    @endif
</div>
