@props([
    'backUrl' => null,
    'backLabel' => null,
])

{{-- Deprecated standalone Plus back control. Navigation lives inside x-site.plus-hero. --}}
@if ($slot->isNotEmpty())
    <div class="flex flex-wrap items-center justify-end gap-3">
        {{ $slot }}
    </div>
@endif
