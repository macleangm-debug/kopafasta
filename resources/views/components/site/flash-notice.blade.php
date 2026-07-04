@props([
    'title',
    'message' => null,
    'tone' => 'success',
])

@php
    $flashMessage = $message ?? session('status') ?? session('warning') ?? session('error');
    $flashTone = $tone;
    if (session('error')) {
        $flashTone = 'error';
    } elseif (session('warning')) {
        $flashTone = 'warning';
    }
@endphp

@if ($flashMessage)
    <div x-data="{ open: true }" x-show="open" x-cloak class="fixed inset-0 z-[10001] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-white shadow-xl ring-1 ring-gray-200 p-6 text-center">
            <div @class([
                'mx-auto mb-4 size-12 rounded-full grid place-items-center text-xl',
                'bg-emerald-100 text-emerald-700' => $flashTone === 'success',
                'bg-amber-100 text-amber-700' => $flashTone === 'warning',
                'bg-red-100 text-red-700' => $flashTone === 'error',
            ])>
                {{ $flashTone === 'error' ? '!' : '✓' }}
            </div>
            <p class="text-sm text-gray-800">{{ $flashMessage }}</p>
            <button type="button" @click="open = false"
                    class="mt-5 inline-flex items-center justify-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                OK
            </button>
        </div>
    </div>
@endif
