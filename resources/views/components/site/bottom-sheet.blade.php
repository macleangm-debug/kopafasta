@props([
    'title' => '',
    'open' => 'open',
])

{{-- Teleport to body so sticky/backdrop-blur ancestors cannot trap position:fixed.
     Mobile only: never show bottom sheets on desktop/web breakpoints. --}}
<template x-teleport="body">
    <div x-show="{{ $open }} && window.matchMedia('(max-width: 1023px)').matches"
         x-cloak
         class="fixed inset-0 z-[10050]"
         role="dialog"
         aria-modal="true"
         x-effect="
            if (typeof document === 'undefined') return;
            const lock = {{ $open }} && window.matchMedia('(max-width: 1023px)').matches;
            document.documentElement.classList.toggle('overflow-hidden', lock);
            document.body.classList.toggle('overflow-hidden', lock);
            document.body.classList.toggle('overscroll-none', lock);
         "
         @resize.window="if (window.matchMedia('(min-width: 1024px)').matches) { {{ $open }} = false }">
        <div class="absolute inset-0 bg-black/40" @click="{{ $open }} = false" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 max-h-[min(90dvh,640px)] flex flex-col rounded-t-2xl bg-white shadow-[0_-8px_40px_rgba(0,0,0,0.18)]"
             style="padding-bottom: env(safe-area-inset-bottom, 0px)"
             x-show="{{ $open }} && window.matchMedia('(max-width: 1023px)').matches"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             @click.stop>
            <div class="flex justify-center pt-3 pb-1 shrink-0">
                <div class="w-10 h-1 rounded-full bg-gray-300"></div>
            </div>
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 shrink-0">
                <h2 class="text-base font-bold text-gray-900">{{ $title }}</h2>
                <button type="button" @click="{{ $open }} = false" class="p-2 -mr-2 rounded-lg text-gray-500 hover:bg-gray-100" aria-label="Close">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto overscroll-contain px-5 py-4">
                {{ $slot }}
            </div>
        </div>
    </div>
</template>
