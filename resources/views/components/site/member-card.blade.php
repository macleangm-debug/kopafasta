@props(['customer'])
@php
    /** @var \App\Models\Customer $customer */
    use App\Support\MemberNumberFormatter;

    $color = $customer->membershipStatusColor();
    $label = $customer->membershipStatusLabel();

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
    $memberNoRaw = MemberNumberFormatter::raw($customer->member_no);
    $memberNoDisplay = MemberNumberFormatter::display($customer->member_no);
    $days    = max(0, (int) $customer->membershipDaysRemaining());
    $duration = (int) (\App\Services\MembershipService::config()['duration_days'] ?? 365);
    $pct     = $duration > 0 ? max(0, min(100, ($days / $duration) * 100)) : 0;
@endphp

<div {{ $attributes->merge(['class' => 'grid grid-cols-1 md:grid-cols-2 gap-4']) }}>

    {{-- Member card --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br {{ $bgGradient }} text-white shadow-lg p-6"
         x-data="{ copied: false, copyNo: @js($memberNoRaw) }">
        <div class="absolute -right-12 -top-12 h-48 w-48 rounded-full bg-white/10"></div>
        <div class="absolute -left-10 -bottom-16 h-40 w-40 rounded-full bg-white/10"></div>

        <div class="flex items-start justify-between relative">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] uppercase tracking-widest text-white/70">KopaFasta Member</p>
                <h3 class="mt-1 text-xl font-bold tracking-wide truncate">{{ $name ?: '—' }}</h3>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $badgeClass }} shrink-0 ml-2">
                {{ $label }}
            </span>
        </div>

        <div class="relative mt-6 rounded-xl bg-black/15 px-4 py-4 ring-1 ring-white/20">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-white/60 mb-2">Membership number</p>
                    <p class="font-mono text-lg sm:text-xl md:text-2xl font-bold tracking-[0.12em] leading-tight break-all">
                        {{ $memberNoDisplay }}
                    </p>
                </div>
                @if ($memberNoRaw)
                    <button type="button"
                            @click="navigator.clipboard.writeText(copyNo).then(() => { copied = true; setTimeout(() => copied = false, 2500); })"
                            class="shrink-0 inline-flex items-center justify-center size-10 rounded-lg bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition"
                            :title="copied ? 'Copied!' : 'Copy membership number'">
                        <template x-if="!copied">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </template>
                        <template x-if="copied">
                            <svg class="size-5 text-emerald-200" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </template>
                    </button>
                @endif
            </div>
            <p x-show="copied" x-cloak x-transition
               class="mt-3 text-xs font-semibold text-emerald-100 bg-emerald-900/30 rounded-lg px-3 py-2 inline-flex items-center gap-1.5">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Membership Number Copied
            </p>
        </div>

        <dl class="mt-5 grid grid-cols-2 gap-3 relative text-sm">
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
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 p-6 flex flex-col items-center justify-center min-h-[220px]">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-4">Membership status</p>
        <div class="relative w-40 h-40 shrink-0">
            <svg class="w-full h-full -rotate-90" viewBox="0 0 128 128">
                <circle cx="64" cy="64" r="48" stroke-width="10" class="stroke-gray-200" fill="none"></circle>
                <circle cx="64" cy="64" r="48" stroke-width="10" class="{{ $ringClass }}" fill="none"
                        stroke-linecap="round"
                        stroke-dasharray="{{ number_format(2 * M_PI * 48, 2, '.', '') }}"
                        stroke-dashoffset="{{ number_format((2 * M_PI * 48) - ($pct / 100) * (2 * M_PI * 48), 2, '.', '') }}"></circle>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center px-3 text-center pointer-events-none">
                <span class="text-3xl font-bold text-gray-900 leading-none">{{ $days }}</span>
                <span class="text-[10px] uppercase tracking-wide text-gray-500 mt-2 leading-tight">days<br>remaining</span>
            </div>
        </div>
        <dl class="mt-5 w-full grid grid-cols-2 gap-3 text-center text-xs">
            <div class="rounded-lg bg-gray-50 p-2">
                <dt class="text-gray-500">Started</dt>
                <dd class="font-semibold text-gray-900 mt-0.5">{{ $issued }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 p-2">
                <dt class="text-gray-500">Expires</dt>
                <dd class="font-semibold text-gray-900 mt-0.5">{{ $expires }}</dd>
            </div>
        </dl>

        @if (! $customer->hasMembership())
            <a href="{{ route('site.membership.renew') }}" class="mt-5 inline-flex items-center bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2 rounded-lg shadow-sm">
                Pay registration fee
            </a>
        @elseif ($customer->isMembershipExpired())
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
