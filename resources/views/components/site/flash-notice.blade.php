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
    <div x-data="{ open: true }"
         x-show="open"
         x-cloak
         x-init="setTimeout(() => open = false, 5000)"
         x-transition
         class="fixed top-4 right-4 z-[10001] w-[min(100%-2rem,24rem)] pointer-events-auto">
        <div @class([
            'rounded-2xl shadow-lg ring-1 px-4 py-3 flex items-start gap-3 bg-white',
            'ring-emerald-200' => $flashTone === 'success',
            'ring-amber-200' => $flashTone === 'warning',
            'ring-red-200' => $flashTone === 'error',
        ])>
            <span @class([
                'mt-0.5 size-8 rounded-full grid place-items-center text-sm font-bold shrink-0',
                'bg-emerald-100 text-emerald-700' => $flashTone === 'success',
                'bg-amber-100 text-amber-700' => $flashTone === 'warning',
                'bg-red-100 text-red-700' => $flashTone === 'error',
            ])>{{ $flashTone === 'error' ? '!' : '✓' }}</span>
            <p class="text-sm text-gray-800 flex-1 pt-1">{{ $flashMessage }}</p>
            <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-lg leading-none shrink-0" aria-label="Dismiss">&times;</button>
        </div>
    </div>
@endif
