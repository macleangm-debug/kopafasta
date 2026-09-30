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
        $days = $daysLeft !== null ? (int) $daysLeft : null;
        $expired = (bool) $expired || ($days !== null && $days < 0);
        $dueToday = ! $expired && $days === 0;
        $urgent = (bool) $urgent || ($days !== null && $days <= 2 && ! $expired);
        $purpose = $purpose ?: null;
        $byLine = filled($date)
            ? __('borrower.loan_profile.deadline_by', ['date' => $date])
            : null;
        $unit = $days === 1
            ? __('borrower.loan_profile.deadline_days_unit_one')
            : __('borrower.loan_profile.deadline_days_unit');
    @endphp
    <div {{ $attributes->class([
        'relative overflow-hidden rounded-2xl px-4 py-3.5 max-w-full text-white shadow-sm',
        'bg-gradient-to-br from-red-700 via-red-600 to-red-500 ring-1 ring-red-400/40' => $urgent || $expired,
        'bg-gradient-to-br from-brand via-brand to-brand-light ring-1 ring-brand/30' => ! $urgent && ! $expired,
    ]) }}>
        <div class="absolute -right-8 -top-8 size-24 rounded-full bg-brand-gold/15 pointer-events-none" aria-hidden="true"></div>
        <div class="relative min-w-0">
            @if ($expired)
                <p class="text-sm font-bold leading-snug">{{ $label ?: __('borrower.loan_profile.document_deadline_expired') }}</p>
                @if (filled($byLine))
                    <p class="text-[11px] font-semibold mt-0.5 text-white/80">{{ $byLine }}</p>
                @endif
            @elseif ($dueToday)
                <p class="text-sm font-black leading-none tracking-tight">{{ __('borrower.loan_profile.document_deadline_due_today') }}</p>
                @if (filled($purpose))
                    <p class="text-xs font-semibold mt-1.5 leading-snug text-white/90">{{ $purpose }}</p>
                @endif
                @if (filled($byLine))
                    <p class="text-[11px] font-semibold mt-0.5 text-white/75">{{ $byLine }}</p>
                @endif
            @elseif ($days !== null)
                <p class="text-xl font-black tabular-nums leading-none tracking-tight">
                    {{ $days }}
                    <span class="text-xs font-bold tracking-normal text-brand-gold">{{ $unit }}</span>
                </p>
                @if (filled($purpose))
                    <p class="text-xs font-semibold mt-1.5 leading-snug text-white/90">{{ $purpose }}</p>
                @endif
                @if (filled($byLine))
                    <p class="text-[11px] font-semibold mt-0.5 text-white/75">{{ $byLine }}</p>
                @endif
            @else
                <p class="text-sm font-bold leading-snug">{{ $label ?? $purpose ?? $byLine }}</p>
            @endif
        </div>
    </div>
@endif
