@props([
    'name' => 'pin',
    'label' => null,
    'required' => true,
    'autocomplete' => 'one-time-code',
    'help' => null,
    'forgotHref' => null,
    'forgotLabel' => null,
])

@php
    $label = $label ?? __('site.auth.pin_label');
    $forgotLabel = $forgotLabel ?? __('site.auth.forgot_pin');
@endphp

<div>
    <div class="flex items-baseline justify-between gap-3 mb-1">
        <label class="kf-auth-label mb-0">{{ $label }}</label>
        @if ($forgotHref)
            <a href="{{ $forgotHref }}" class="text-xs text-brand font-medium hover:underline">{{ $forgotLabel }}</a>
        @endif
    </div>
    <input type="password"
           name="{{ $name }}"
           inputmode="numeric"
           maxlength="4"
           pattern="\d{4}"
           data-digits-only
           autocomplete="{{ $autocomplete }}"
           placeholder="••••"
           @if ($required) required @endif
           {{ $attributes->merge(['class' => 'kf-auth-pin']) }}>
    @if ($help)
        <p class="kf-auth-help">{{ $help }}</p>
    @endif
    @error($name)
        <p class="kf-auth-error">{{ $message }}</p>
    @enderror
</div>
