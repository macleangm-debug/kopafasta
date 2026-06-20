{{-- Internal/admin only — borrowers see final deposit on the card/detail, not markup breakdown. --}}
@props(['asset', 'internal' => false])

@if ($internal && ($asset['supplier_deposit'] ?? 0) > 0)
    <div class="mt-3 rounded-xl bg-slate-50 ring-1 ring-slate-200 p-3 text-xs space-y-1">
        <p class="font-semibold text-gray-700 uppercase tracking-wide text-[10px]">Deposit breakdown (internal)</p>
        <div class="flex justify-between"><span class="text-gray-500">Supplier deposit</span><span class="font-semibold">{{ format_money($asset['supplier_deposit']) }}</span></div>
        @if (($asset['deposit_markup_amount'] ?? 0) > 0)
            <div class="flex justify-between"><span class="text-gray-500">Company markup ({{ rtrim(rtrim(number_format((float) ($asset['deposit_markup_percent'] ?? 0), 2), '0'), '.') }}%)</span><span class="font-semibold">{{ format_money($asset['deposit_markup_amount']) }}</span></div>
        @endif
        <div class="flex justify-between pt-1 border-t border-slate-200"><span class="text-gray-700 font-semibold">Customer deposit</span><span class="font-bold text-gray-900">{{ format_money($asset['deposit'] ?? 0) }}</span></div>
    </div>
@endif
