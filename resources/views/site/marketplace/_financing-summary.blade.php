@php
    $rows = [
        [
            'label' => __('borrower.marketplace.asset_value'),
            'amount' => $asset['asset_value'] ?? 0,
            'class' => 'text-gray-900',
        ],
        [
            'label' => __('borrower.marketplace.deposit'),
            'amount' => $asset['deposit'] ?? 0,
            'class' => 'text-brand',
        ],
        [
            'label' => __('borrower.marketplace.loan_amount'),
            'amount' => $asset['remaining_loan'] ?? 0,
            'class' => 'text-gray-900',
        ],
    ];
@endphp

<div data-financing-summary class="rounded-xl bg-gray-50 ring-1 ring-gray-200 overflow-hidden">
    {{-- Mobile: one compact card. Desktop: three aligned cards with the same nowrap amounts. --}}
    <div class="lg:hidden divide-y divide-gray-200">
        @foreach ($rows as $row)
            <div class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                <p class="text-xs font-semibold text-gray-600 shrink-0">{{ $row['label'] }}</p>
                <p class="font-bold tabular-nums whitespace-nowrap text-right {{ $row['class'] }}"
                   style="font-size: clamp(0.92rem, 3.8vw, 1.05rem)">{{ format_money($row['amount'], false, 0) }}</p>
            </div>
        @endforeach
    </div>
    <div class="hidden lg:grid lg:grid-cols-3 divide-y lg:divide-y-0 lg:divide-x divide-gray-200">
        @foreach ($rows as $row)
            <div class="px-4 py-3">
                <p class="text-[11px] uppercase tracking-widest text-gray-600 font-semibold">{{ $row['label'] }}</p>
                <p class="mt-1 font-bold tabular-nums whitespace-nowrap {{ $row['class'] }}"
                   style="font-size: clamp(0.95rem, 1.15vw, 1.125rem)">{{ format_money($row['amount'], false, 0) }}</p>
            </div>
        @endforeach
    </div>
    @if (! empty($asset['max_tenure_months']))
        <p class="px-4 py-2.5 text-xs text-gray-600 border-t border-gray-200">{{ __('borrower.marketplace.up_to_months', ['months' => (int) $asset['max_tenure_months']]) }}</p>
    @endif
</div>
