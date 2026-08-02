@props(['partner'])

@php
    $photoUrl = app(\App\Services\PartnerProfileService::class)->frontPhotoUrl($partner);
    $initial = strtoupper(substr((string) ($partner->name ?? '?'), 0, 1) ?: '?');
    $code = $partner->partner_number ?? $partner->vendor_number ?? $partner->code ?? null;
    $category = $partner instanceof \App\Models\Lender ? 'investor' : ($partner->category ?? null);
@endphp

<div class="glass-card p-5 sm:p-6 mb-6">
    <div class="flex items-center gap-4">
        <div class="shrink-0">
            @if ($photoUrl)
                <img src="{{ $photoUrl }}" alt="" class="size-16 sm:size-20 rounded-2xl object-cover ring-2 ring-amber-200 bg-gray-100">
            @else
                <div class="size-16 sm:size-20 rounded-2xl bg-gradient-to-br from-brand to-brand-light text-white grid place-items-center text-xl font-bold ring-2 ring-brand/20">
                    {{ $initial }}
                </div>
            @endif
        </div>

        <div class="min-w-0 flex-1">
            <h2 class="text-lg sm:text-xl font-bold text-gray-900 truncate">{{ $partner->name }}</h2>
            <div class="flex flex-wrap items-center gap-2 mt-1">
                @if ($category)
                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-brand-muted text-brand capitalize">{{ str_replace('_', ' ', $category) }}</span>
                @endif
                @if ($code)
                    <span class="text-xs font-mono text-gray-500">{{ $code }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
