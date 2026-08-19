@props(['customer'])

@php
    $portalContext = app(\App\Services\PortalContextService::class);
    $displayName = $portalContext->displayName($customer);
    $photoUrl = app(\App\Services\FaceVerificationService::class)->avatarUrl($customer);
    $initial = strtoupper(substr($displayName, 0, 1) ?: '?');
    $membershipActive = ($customer->membership_status ?? '') === 'active'
        && ($customer->membership_expires_at === null || $customer->membership_expires_at->isFuture());
    $membershipLabel = $membershipActive
        ? __('borrower.profile.member_active')
        : __('borrower.profile.member_inactive');
    $membershipClass = $membershipActive
        ? 'bg-emerald-100 text-emerald-800'
        : 'bg-gray-100 text-gray-700';
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
            <h2 class="text-lg sm:text-xl font-bold text-gray-900 truncate">{{ $displayName }}</h2>
            <div class="flex flex-wrap items-center gap-2 mt-1">
                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $membershipClass }}">{{ $membershipLabel }}</span>
                <span class="text-xs font-mono text-gray-500">{{ $customer->customer_number }}</span>
            </div>
        </div>
    </div>
</div>
