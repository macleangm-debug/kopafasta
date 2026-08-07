@props([
    'status' => null,
])

@if (! empty($status) && ! empty($status['repossessed']))
    @php
        $days = $status['days_until_auction'] ?? null;
        $tone = match ($status['label'] ?? '') {
            'sold' => 'bg-emerald-50 ring-emerald-200 text-emerald-900',
            'listed', 'auction_assigned' => 'bg-amber-50 ring-amber-200 text-amber-950',
            'cancelled' => 'bg-gray-50 ring-gray-200 text-gray-700',
            default => ($days !== null && $days <= 1)
                ? 'bg-red-50 ring-red-200 text-red-900'
                : 'bg-orange-50 ring-orange-200 text-orange-950',
        };
    @endphp
    <div {{ $attributes->merge(['class' => 'rounded-xl ring-1 p-4 '.$tone]) }}>
        <p class="text-[10px] uppercase tracking-widest font-semibold opacity-80">{{ __('borrower.loan_servicing.repossession_title') }}</p>
        <p class="mt-1 text-sm font-bold">
            {{ __('borrower.loan_servicing.repossession_status') }}
        </p>
        <p class="mt-1 text-xs opacity-90">
            @if (($status['label'] ?? '') === 'sold')
                {{ __('borrower.loan_servicing.auction_sold') }}
            @elseif (($status['label'] ?? '') === 'listed')
                {{ __('borrower.loan_servicing.auction_listed') }}
            @elseif (($status['label'] ?? '') === 'auction_assigned')
                {{ __('borrower.loan_servicing.auction_assigned') }}
            @elseif (($status['label'] ?? '') === 'cancelled')
                {{ __('borrower.loan_servicing.auction_cancelled') }}
            @elseif ($days !== null && $days > 0)
                {{ __('borrower.loan_servicing.days_until_auction', ['days' => $days, 'date' => optional($status['auction_eligible_at'])->format('d M Y')]) }}
            @elseif ($days !== null && $days === 0)
                {{ __('borrower.loan_servicing.auction_today') }}
            @elseif ($days !== null && $days < 0)
                {{ __('borrower.loan_servicing.auction_overdue') }}
            @else
                {{ __('borrower.loan_servicing.repossessed_generic') }}
            @endif
        </p>
        @if (! empty($status['repossessed_at']))
            <p class="mt-2 text-[11px] opacity-70">
                {{ __('borrower.loan_servicing.repossessed_on', ['date' => $status['repossessed_at']->format('d M Y')]) }}
            </p>
        @endif
    </div>
@endif
