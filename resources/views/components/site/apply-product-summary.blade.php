{{-- Selected product summary during apply wizard. Parent Alpine: current, formatTzs --}}
<div x-show="current" x-cloak class="mb-6 rounded-2xl ring-1 ring-amber-200 bg-amber-50/50 p-4 sm:p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <p class="text-[10px] uppercase tracking-widest text-amber-700 font-semibold">{{ __('borrower.apply.product_summary.label') }}</p>
            <h2 class="text-lg font-bold text-gray-900 mt-0.5" x-text="current?.name"></h2>
            <p class="text-xs font-semibold text-amber-800 mt-1" x-text="current?.loan_type"></p>
        </div>
        <div class="text-right shrink-0">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.product_summary.application_fee') }}</p>
            <p class="text-sm font-bold text-gray-900 mt-0.5" x-text="formatTzs(current?.application_fee ?? 0)"></p>
        </div>
    </div>
    <ul class="mt-3 space-y-1 text-xs text-gray-700" x-show="current?.features?.length">
        <template x-for="(feature, fi) in current.features" :key="fi">
            <li class="flex gap-2"><span class="text-amber-600 shrink-0">•</span><span x-text="feature"></span></li>
        </template>
    </ul>
</div>
