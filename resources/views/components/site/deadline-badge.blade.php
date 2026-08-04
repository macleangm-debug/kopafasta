@props([
    'label' => null,
    'daysLeft' => null,
    'date' => null,
    'purpose' => null,
    'urgent' => false,
    'expired' => false,
])

@if (filled($label) || $daysLeft !== null || filled($date))
    @php
        $urgent = (bool) $urgent || ($daysLeft !== null && (int) $daysLeft <= 2 && ! $expired);
        $expired = (bool) $expired || ($daysLeft !== null && (int) $daysLeft < 0);
        $showCountdown = $daysLeft !== null && ! $expired;
        $purpose = $purpose ?: null;
        $byLine = filled($date)
            ? __('borrower.loan_profile.deadline_by', ['date' => $date])
            : null;
    @endphp
    <div @class([
        'mt-3 inline-flex items-center gap-3 rounded-2xl px-4 py-3.5 ring-2 shadow-sm max-w-full',
        'bg-red-50 ring-red-300 text-red-900' => $urgent || $expired,
        'bg-brand-gold/25 ring-brand-gold/60 text-brand' => ! $urgent && ! $expired,
    ])>
        <span class="text-2xl leading-none shrink-0" aria-hidden="true">⏱</span>
        @if ($showCountdown)
            <div class="min-w-0">
                <p class="text-2xl sm:text-3xl font-black tabular-nums leading-none tracking-tight">
                    {{ max(0, (int) $daysLeft) }}
                    <span class="text-sm sm:text-base font-bold tracking-normal">{{ __('borrower.loan_profile.deadline_days_unit') }}</span>
                </p>
                @if (filled($purpose))
                    <p class="text-sm font-bold mt-1.5 leading-snug">{{ $purpose }}</p>
                @endif
                @if (filled($byLine))
                    <p class="text-xs font-semibold mt-0.5 opacity-80">{{ $byLine }}</p>
                @elseif (filled($label) && ! filled($purpose))
                    <p class="text-xs sm:text-sm font-semibold mt-1.5 opacity-90">{{ $label }}</p>
                @endif
            </div>
        @else
            <p class="text-sm sm:text-base font-bold leading-snug">{{ $label ?? $purpose ?? $byLine }}</p>
        @endif
    </div>
@endif
