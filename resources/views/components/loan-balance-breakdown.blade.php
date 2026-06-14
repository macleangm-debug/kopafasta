@props(['breakdown', 'expanded' => false])

@php
    $breakdown = $breakdown ?? [];
@endphp

<div x-data="{ open: @json($expanded) }" class="rounded-xl ring-1 ring-gray-200 bg-white overflow-hidden">
    <button type="button" @click="open = !open" class="w-full px-4 py-3 flex items-center justify-between text-left hover:bg-gray-50">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-gray-400">Outstanding balance</p>
            <p class="text-xl font-bold text-gray-900">{{ format_money($breakdown['total_outstanding'] ?? 0) }}</p>
        </div>
        <span class="text-xs font-semibold text-amber-700" x-text="open ? 'Hide breakdown' : 'Show breakdown'"></span>
    </button>
    <div x-show="open" x-cloak class="border-t border-gray-100 px-4 py-3 grid sm:grid-cols-2 gap-3 text-sm">
        <div class="flex justify-between gap-3"><span class="text-gray-500">Principal outstanding</span><span class="font-semibold">{{ format_money($breakdown['principal_outstanding'] ?? 0) }}</span></div>
        <div class="flex justify-between gap-3"><span class="text-gray-500">Interest outstanding</span><span class="font-semibold">{{ format_money($breakdown['interest_outstanding'] ?? 0) }}</span></div>
        <div class="flex justify-between gap-3"><span class="text-gray-500">Penalty outstanding</span><span class="font-semibold">{{ format_money($breakdown['penalty_outstanding'] ?? 0) }}</span></div>
        <div class="flex justify-between gap-3"><span class="text-gray-500">Recovery costs</span><span class="font-semibold">{{ format_money($breakdown['recovery_costs'] ?? 0) }}</span></div>
        <div class="sm:col-span-2 flex justify-between gap-3 pt-2 border-t border-gray-100 font-semibold">
            <span>Total outstanding</span><span>{{ format_money($breakdown['total_outstanding'] ?? 0) }}</span>
        </div>
    </div>
</div>
