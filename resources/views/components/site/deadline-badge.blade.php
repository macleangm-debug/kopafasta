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
        'inline-flex items-center gap-2.5 rounded-xl px-3 py-2.5 ring-1 shadow-sm max-w-full',
        'bg-red-50 ring-red-300 text-red-900' => $urgent || $expired,
        'bg-brand-gold/25 ring-brand-gold/60 text-brand' => ! $urgent && ! $expired,
    ]) }}>
        <span class="text-lg leading-none shrink-0" aria-hidden="true">⏱</span>
        <div class="min-w-0">
            @if ($expired)
                <p class="text-sm font-bold leading-snug">{{ $label ?: __('borrower.loan_profile.document_deadline_expired') }}</p>
                @if (filled($byLine))
                    <p class="text-[11px] font-semibold mt-0.5 opacity-80">{{ $byLine }}</p>
                @endif
            @elseif ($dueToday)
                <p class="text-sm font-black leading-none tracking-tight">{{ __('borrower.loan_profile.document_deadline_due_today') }}</p>
                @if (filled($purpose))
                    <p class="text-xs font-bold mt-1 leading-snug">{{ $purpose }}</p>
                @endif
                @if (filled($byLine))
                    <p class="text-[11px] font-semibold mt-0.5 opacity-80">{{ $byLine }}</p>
                @endif
            @elseif ($days !== null)
                <p class="text-xl font-black tabular-nums leading-none tracking-tight">
                    {{ $days }}
                    <span class="text-xs font-bold tracking-normal">{{ $unit }}</span>
                </p>
                @if (filled($purpose))
                    <p class="text-xs font-bold mt-1 leading-snug">{{ $purpose }}</p>
                @endif
                @if (filled($byLine))
                    <p class="text-[11px] font-semibold mt-0.5 opacity-80">{{ $byLine }}</p>
                @endif
            @else
                <p class="text-sm font-bold leading-snug">{{ $label ?? $purpose ?? $byLine }}</p>
            @endif
        </div>
    </div>
@endif
