@props([
    'action' => 'login',
])

@php
    $turnstile = app(\App\Services\TurnstileService::class);
@endphp

@if ($turnstile->enabled())
    <div class="cf-turnstile my-3" data-sitekey="{{ $turnstile->siteKey() }}" data-action="{{ $action }}"></div>
    @once
        @push('scripts')
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endpush
    @endonce
    @error('cf-turnstile-response')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
@endif
