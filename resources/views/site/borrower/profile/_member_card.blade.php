@props(['customer'])

@php
    $portalContext = app(\App\Services\PortalContextService::class);
    $displayName = $portalContext->displayName($customer);
    $facePhoto = app(\App\Services\FaceVerificationService::class)->latestByAngle($customer)->get('front');
    $photoUrl = $facePhoto?->file_path ? asset('storage/'.$facePhoto->file_path) : null;
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
    <div class="flex items-start gap-4 sm:gap-5">
        <div class="shrink-0">
            @if ($photoUrl)
                <img src="{{ $photoUrl }}" alt="" class="size-20 sm:size-24 rounded-2xl object-cover ring-2 ring-amber-200 bg-gray-100">
            @else
                <div class="size-20 sm:size-24 rounded-2xl bg-gradient-to-br from-brand to-brand-light text-white grid place-items-center text-2xl font-bold ring-2 ring-brand/20">
                    {{ $initial }}
                </div>
            @endif
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <h2 class="text-lg sm:text-xl font-bold text-gray-900 truncate">{{ $displayName }}</h2>
                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $membershipClass }}">{{ $membershipLabel }}</span>
            </div>
            <p class="text-xs font-mono text-gray-500">{{ $customer->customer_number }}</p>

            <dl class="mt-4 grid sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">{{ __('borrower.profile.fields.phone') }}</dt>
                    <dd class="font-medium mt-0.5">{{ $customer->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('borrower.profile.fields.email') }}</dt>
                    <dd class="font-medium mt-0.5 truncate">{{ $customer->email ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('borrower.profile.fields.region') }}</dt>
                    <dd class="font-medium mt-0.5">{{ $customer->region ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('borrower.profile.fields.national_id') }}</dt>
                    <dd class="font-medium mt-0.5 font-mono">{{ $customer->national_id ?: '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
