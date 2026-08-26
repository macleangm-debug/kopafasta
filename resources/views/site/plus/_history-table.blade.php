@php
    $rows = $rows ?? [];
    $dateLabel = $dateLabel ?? __('plus.money.col_date');
    $whatLabel = $whatLabel ?? __('plus.money.col_what');
    $amountLabel = $amountLabel ?? __('plus.money.amount');
@endphp
<div x-data="{ shown: 10 }">
    <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-2">{{ $title }}</p>
    <div class="overflow-x-auto rounded-2xl ring-1 ring-gray-100 bg-white">
        <table class="w-full text-sm">
            <thead class="text-[10px] uppercase tracking-widest text-gray-500">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold">{{ $dateLabel }}</th>
                    <th class="text-left px-4 py-3 font-semibold">{{ $whatLabel }}</th>
                    <th class="text-right px-4 py-3 font-semibold">{{ $amountLabel }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $i => $row)
                    <tr class="border-t border-gray-50" x-show="{{ $i }} < shown">
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $row['date'] }}</td>
                        <td class="px-4 py-3">{{ $row['label'] }}</td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums {{ ! empty($row['in']) ? 'text-emerald-700' : 'text-gray-900' }}">
                            {{ ! empty($row['in']) ? '+' : '−' }}{{ format_money($row['amount']) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6">
                            {{ $empty ?? '' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if (count($rows) > 10)
        <button type="button" class="mt-3 text-sm font-semibold text-brand" x-show="shown < {{ count($rows) }}" @click="shown += 10">{{ __('plus.load_more') }}</button>
    @endif
</div>
