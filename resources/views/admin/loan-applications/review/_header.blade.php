@php
    $customer = $review['customer'];
    $product = $review['product'];
    $risk = $review['risk'];
    $riskTone = match ($risk['band']) {
        'low'    => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'medium' => 'bg-amber-50 text-amber-900 ring-amber-200',
        default  => 'bg-red-50 text-red-800 ring-red-200',
    };
@endphp

<div class="grid lg:grid-cols-3 gap-4 mb-6">
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Facility summary</p>
                <p class="text-sm font-semibold text-gray-900 mt-0.5">Requested terms and offer position</p>
            </div>
            <p class="text-xs text-gray-500 shrink-0">{{ optional($record->submitted_at)->format('d M Y H:i') ?? 'Not submitted' }}</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">Amount requested</p>
                <p class="text-sm font-bold text-gray-900 mt-1">{{ format_money((float) $record->requested_amount) }}</p>
            </div>
            @if ($record->recommended_amount && (float) $record->recommended_amount !== (float) $record->requested_amount)
                <div class="rounded-lg bg-sky-50 ring-1 ring-sky-100 px-3 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-sky-700">Recommended</p>
                    <p class="text-sm font-bold text-sky-900 mt-1">{{ format_money((float) $record->recommended_amount) }}</p>
                </div>
            @endif
            @if ($record->offered_amount)
                <div class="rounded-lg bg-amber-50 ring-1 ring-amber-100 px-3 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-amber-700">Offered</p>
                    <p class="text-sm font-bold text-amber-900 mt-1">{{ format_money((float) $record->offered_amount) }}</p>
                </div>
            @endif
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">Tenure</p>
                <p class="text-sm font-bold text-gray-900 mt-1">{{ $record->offered_tenure_months ?? $record->requested_tenure_months }} months</p>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">Submitted</p>
                <p class="text-sm font-bold text-gray-900 mt-1">{{ optional($record->submitted_at)->format('d M Y') ?? '—' }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">Membership #</p>
                <p class="text-sm font-bold text-gray-900 mt-1 font-mono">{{ $customer->member_no ?? '—' }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-xl shadow-sm ring-1 p-5 {{ $riskTone }}">
        <p class="text-[10px] uppercase tracking-widest font-semibold opacity-80">Application risk score</p>
        <div class="flex items-end gap-2 mt-2">
            <span class="text-4xl font-bold leading-none">{{ $risk['score'] }}</span>
            <span class="text-sm font-semibold pb-1">/ 100</span>
        </div>
        <p class="text-sm font-semibold mt-2">{{ $risk['label'] }}</p>
        <p class="text-xs mt-2 opacity-90">
            System recommendation:
            <span class="font-semibold uppercase">{{ $risk['recommendation'] }}</span>
        </p>
        @if (! empty($risk['factors']))
            <ul class="mt-3 space-y-1 text-xs opacity-90">
                @foreach (array_slice($risk['factors'], 0, 4) as $factor)
                    <li>• {{ $factor }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    @foreach ($review['checklist'] as $item)
        @php
            $tone = match ($item['tone']) {
                'emerald' => 'bg-emerald-50 ring-emerald-200 text-emerald-800',
                'amber'   => 'bg-amber-50 ring-amber-200 text-amber-900',
                'red'     => 'bg-red-50 ring-red-200 text-red-800',
                default   => 'bg-gray-50 ring-gray-200 text-gray-700',
            };
        @endphp
        <div class="rounded-xl ring-1 px-4 py-3 {{ $tone }}">
            <p class="text-[10px] uppercase tracking-widest font-semibold opacity-80">{{ $item['label'] }}</p>
            <p class="text-sm font-semibold mt-1">{{ $item['detail'] }}</p>
        </div>
    @endforeach
</div>
