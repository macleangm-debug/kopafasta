@props(['customer'])
@php
    /** @var \App\Models\Customer $customer */
    $color = $customer->membershipStatusColor();
    $label = $customer->membershipStatusLabel();

    // Tailwind class maps (kept literal so JIT picks them up).
    $bgGradient = match ($color) {
        'green'  => 'from-emerald-500 via-emerald-600 to-emerald-700',
        'orange' => 'from-amber-500 via-amber-600 to-amber-700',
        'red'    => 'from-rose-500 via-rose-600 to-rose-700',
        default  => 'from-slate-500 via-slate-600 to-slate-700',
    };
    $badgeClass = match ($color) {
        'green'  => 'bg-emerald-100 text-emerald-800',
        'orange' => 'bg-amber-100 text-amber-800',
        'red'    => 'bg-rose-100 text-rose-800',
        default  => 'bg-slate-100 text-slate-800',
    };
    $ringClass = match ($color) {
        'green'  => 'stroke-emerald-400',
        'orange' => 'stroke-amber-400',
        'red'    => 'stroke-rose-400',
        default  => 'stroke-slate-400',
    };

    $issued  = optional($customer->membership_issued_at)->format('d M Y') ?? '—';
    $expires = optional($customer->membership_expires_at)->format('d M Y') ?? '—';
    $name    = strtoupper(trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')));
    $memberNo = $customer->member_no ?? '—';
    $days    = max(0, (int) $customer->membershipDaysRemaining());
    $duration = (int) (\App\Services\MembershipService::config()['duration_days'] ?? 365);
    $pct     = $duration > 0 ? max(0, min(100, ($days / $duration) * 100)) : 0;
    $circ    = 2 * M_PI * 44; // r=44
    $dash    = $circ - ($pct / 100) * $circ;
@endphp

<div {{ $attributes->merge(['class' => 'grid grid-cols-1 md:grid-cols-2 gap-4']) }}>

    {{-- Member card --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br {{ $bgGradient }} text-white shadow-lg p-6">
        <div class="absolute -right-12 -top-12 h-48 w-48 rounded-full bg-white/10"></div>
        <div class="absolute -left-10 -bottom-16 h-40 w-40 rounded-full bg-white/10"></div>

        <div class="flex items-start justify-between relative">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-white/70">KopaFasta Member</p>
                <h3 class="mt-1 text-xl font-bold tracking-wide">{{ $name ?: '—' }}</h3>
                <p class="mt-1 text-sm font-mono text-white/90">{{ $memberNo }}</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $badgeClass }}">
                {{ $label }}
            </span>
        </div>

        <dl class="mt-6 grid grid-cols-2 gap-3 relative text-sm">
            <div>
                <dt class="text-[10px] uppercase tracking-wider text-white/70">Issued</dt>
                <dd class="font-semibold">{{ $issued }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-wider text-white/70">Expires</dt>
                <dd class="font-semibold">{{ $expires }}</dd>
            </div>
        </dl>
    </div>

    {{-- Circular status widget --}}
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 p-6 flex flex-col items-center justify-center">
        <p class="text-xs uppercase tracking-wider text-gray-500">Membership Status</p>
        <div class="relative mt-3">
            <svg class="w-32 h-32 -rotate-90">
                <circle cx="64" cy="64" r="44" stroke-width="10" class="stroke-gray-200" fill="none"></circle>
                <circle cx="64" cy="64" r="44" stroke-width="10" class="{{ $ringClass }}" fill="none"
                        stroke-linecap="round"
                        stroke-dasharray="{{ number_format($circ, 2, '.', '') }}"
                        stroke-dashoffset="{{ number_format($dash, 2, '.', '') }}"></circle>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-3xl font-bold text-gray-900">{{ $days }}</span>
                <span class="text-[10px] uppercase tracking-wider text-gray-500">days remaining</span>
            </div>
        </div>

        @if ($customer->isMembershipExpired())
            <a href="{{ route('site.membership.renew') }}" class="mt-5 inline-flex items-center bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold px-4 py-2 rounded-lg shadow-sm">
                Renew now
            </a>
        @elseif ($customer->isMembershipExpiringSoon(30))
            <a href="{{ route('site.membership.renew') }}" class="mt-5 inline-flex items-center bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2 rounded-lg shadow-sm">
                Renew membership
            </a>
        @endif
    </div>
</div>
