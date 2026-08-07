@props([
    'total' => 0,
    'penalty' => 0,
    'recovery' => 0,
    'principal' => null,
    'compact' => false,
])

@php
    $total = (float) $total;
    $penalty = (float) $penalty;
    $recovery = (float) $recovery;
    $showDetail = $penalty > 0 || $recovery > 0;
@endphp

@if ($compact)
    <div class="space-y-1 text-sm">
        <div class="flex justify-between gap-3">
            <span class="text-gray-500">{{ __('borrower.loans_page.outstanding') }}</span>
            <span class="font-semibold tabular-nums">{{ format_money($total) }}</span>
        </div>
        @if ($showDetail)
            @if ($penalty > 0)
                <div class="flex justify-between gap-3 text-xs">
                    <span class="text-gray-500">{{ __('borrower.loan_servicing.penalty_outstanding') }}</span>
                    <span class="font-semibold text-red-700 tabular-nums">{{ format_money($penalty) }}</span>
                </div>
            @endif
            @if ($recovery > 0)
                <div class="flex justify-between gap-3 text-xs">
                    <span class="text-gray-500">{{ __('borrower.loan_servicing.recovery_total') }}</span>
                    <span class="font-semibold text-amber-800 tabular-nums">{{ format_money($recovery) }}</span>
                </div>
            @endif
        @endif
    </div>
@else
    <div class="rounded-xl ring-1 ring-gray-200 bg-white p-4 text-sm space-y-2">
        <div class="flex justify-between gap-3">
            <span class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.outstanding') }}</span>
            <span class="text-base font-bold tabular-nums">{{ format_money($total) }}</span>
        </div>
        @if ($principal !== null)
            <div class="flex justify-between gap-3 text-xs text-gray-600">
                <span>{{ __('borrower.loan_servicing.principal_outstanding') }}</span>
                <span class="font-semibold tabular-nums">{{ format_money($principal) }}</span>
            </div>
        @endif
        @if ($penalty > 0)
            <div class="flex justify-between gap-3 text-xs">
                <span class="text-red-700">{{ __('borrower.loan_servicing.penalty_outstanding') }}</span>
                <span class="font-semibold text-red-800 tabular-nums">{{ format_money($penalty) }}</span>
            </div>
        @endif
        @if ($recovery > 0)
            <div class="flex justify-between gap-3 text-xs">
                <span class="text-amber-800">{{ __('borrower.loan_servicing.recovery_total') }}</span>
                <span class="font-semibold text-amber-900 tabular-nums">{{ format_money($recovery) }}</span>
            </div>
        @endif
    </div>
@endif
