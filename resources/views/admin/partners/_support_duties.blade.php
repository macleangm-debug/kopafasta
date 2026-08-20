@php
    $duties = $duties ?? app(\App\Services\PartnerStaffService::class)->duties();
    $compact = (bool) ($compact ?? false);
@endphp

<div class="{{ $compact ? 'rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3' : 'rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-5 py-4' }}">
    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Partner support duties</p>
    <p class="text-sm font-semibold text-gray-900 mt-0.5">Admin and Partner support own this desk — not screening.</p>
    <ol class="mt-2 {{ $compact ? 'text-xs' : 'text-sm' }} text-gray-600 list-decimal ml-4 space-y-1">
        @foreach ($duties as $duty)
            <li>{{ $duty }}</li>
        @endforeach
    </ol>
</div>
