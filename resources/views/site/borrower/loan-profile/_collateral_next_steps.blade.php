@props([
    'audience' => 'borrower', // borrower|guarantor
    'progress' => null,
])

@php
    $status = $progress['status'] ?? 'pay_valuation';
    if ($audience === 'guarantor') {
        $steps = [
            ['label' => __('borrower.collateral_secure.next_step_your_part_done'), 'done' => true],
            [
                'label' => __('borrower.collateral_secure.next_step_borrower_valuation'),
                'done' => in_array($status, ['awaiting_valuer', 'in_progress', 'completed'], true),
                'current' => ! in_array($status, ['awaiting_valuer', 'in_progress', 'completed'], true),
            ],
        ];
    } else {
        // 4 borrower steps: pay → wait for valuer → in progress → completed
        $steps = [
            [
                'label' => $status === 'pay_valuation' || $status === 'idle'
                    ? __('borrower.collateral_secure.valuation_status_pay')
                    : ($status === 'waiting_payment'
                        ? __('borrower.collateral_secure.valuation_status_waiting_payment')
                        : __('borrower.collateral_secure.next_step_valuation_paid')),
                'done' => in_array($status, ['awaiting_valuer', 'in_progress', 'completed'], true),
                'current' => in_array($status, ['pay_valuation', 'waiting_payment', 'idle'], true),
            ],
            [
                'label' => __('borrower.collateral_secure.valuation_status_awaiting_valuer'),
                'done' => in_array($status, ['in_progress', 'completed'], true),
                'current' => $status === 'awaiting_valuer',
            ],
            [
                'label' => __('borrower.collateral_secure.valuation_status_in_progress'),
                'done' => $status === 'completed',
                'current' => $status === 'in_progress',
            ],
            [
                'label' => __('borrower.collateral_secure.valuation_status_completed'),
                'done' => $status === 'completed',
                'current' => false,
            ],
        ];
    }
@endphp

<div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
    <p class="text-[10px] uppercase tracking-widest text-brand font-bold mb-3">{{ __('borrower.collateral_secure.next_heading') }}</p>
    @if ($audience === 'borrower' && ! empty($progress['label']))
        <p class="text-sm font-semibold text-gray-800 mb-3">{{ $progress['label'] }}</p>
    @endif
    <ol class="space-y-2.5">
        @foreach ($steps as $step)
            <li class="flex items-center gap-3 text-sm">
                @if (! empty($step['done']))
                    <span class="size-6 rounded-full bg-emerald-100 text-emerald-800 grid place-items-center text-[11px] font-bold shrink-0">✓</span>
                    <span class="font-semibold text-emerald-900">{{ $step['label'] }}</span>
                @elseif (! empty($step['current']))
                    <span class="size-6 rounded-full bg-brand text-white grid place-items-center text-[11px] font-bold shrink-0">{{ $loop->iteration }}</span>
                    <span class="font-extrabold text-gray-900">{{ $step['label'] }}</span>
                @else
                    <span class="size-6 rounded-full bg-gray-100 text-gray-500 grid place-items-center text-[11px] font-bold shrink-0">{{ $loop->iteration }}</span>
                    <span class="font-medium text-gray-500">{{ $step['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</div>
