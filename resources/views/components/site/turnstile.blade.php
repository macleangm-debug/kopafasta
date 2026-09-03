@props([
    'action' => 'login',
    'showErrors' => true,
    'inline' => false,
])

@php
    $turnstile = app(\App\Services\TurnstileService::class);
@endphp

@if ($turnstile->enabled())
    <div {{ $attributes->class(['cf-turnstile my-3']) }} data-sitekey="{{ $turnstile->siteKey() }}" data-action="{{ $action }}"></div>
    @if ($inline)
        @once
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endonce
    @else
        @once
            @push('scripts')
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            @endpush
        @endonce
    @endif
    @if ($showErrors)
        @error('cf-turnstile-response')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    @endif
@endif
