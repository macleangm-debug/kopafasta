{{-- Compact wizard chrome — one slim bar for all loan products. Alpine: phase, current, steps, step, draftReference --}}
<div class="mb-4 rounded-xl bg-white ring-1 ring-brand/10 px-3.5 sm:px-4 py-2.5">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <h1 class="text-sm sm:text-base font-bold text-brand truncate"
                    x-text="isEditHop()
                        ? (['guarantor'].includes(stepKey) ? @js(__('borrower.apply.change_guarantor')) : @js(__('borrower.apply.submit_step.edit_quote')))
                        : (current?.name || @js(__('borrower.apply.wizard_title')))"></h1>
                <span class="text-[11px] font-mono text-gray-500" x-show="!isEditHop() && current?.code" x-cloak x-text="current.code"></span>
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-semibold" x-show="!isEditHop() && (phase === 'details' || phase === 'application')" x-cloak>
                <span class="inline-flex items-center gap-1"
                      :class="phase === 'details' ? 'text-brand' : 'text-emerald-700'">
                    <span class="size-4 rounded-full grid place-items-center text-[9px]"
                          :class="phase === 'details' ? 'bg-brand text-white' : 'bg-emerald-100 text-emerald-800'">1</span>
                    {{ __('borrower.apply.wizard_phases.details') }}
                </span>
                <span class="text-gray-300" aria-hidden="true">→</span>
                <span class="inline-flex items-center gap-1"
                      :class="phase === 'application' ? 'text-brand' : 'text-gray-400'">
                    <span class="size-4 rounded-full grid place-items-center text-[9px]"
                          :class="phase === 'application' ? 'bg-brand text-white' : 'bg-gray-100 text-gray-500'">2</span>
                    {{ __('borrower.apply.wizard_phases.application') }}
                </span>
                <span class="text-gray-300" x-show="phase === 'application' && steps?.length" x-cloak>·</span>
                <span class="text-gray-500 font-medium tabular-nums" x-show="phase === 'application' && steps?.length" x-cloak
                      x-text="(step + 1) + '/' + steps.length"></span>
            </div>
            <p class="mt-1 text-[11px] text-gray-500" x-show="isEditHop()" x-cloak>{{ __('borrower.apply.edit_hop_hint') }}</p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <span x-show="draftReference" x-cloak
                  class="hidden sm:inline-flex items-center gap-1.5 rounded-lg bg-gray-50 ring-1 ring-gray-200 px-2 py-1 font-mono text-[10px] font-semibold text-gray-800"
                  x-text="draftReference"></span>
            <a :href="profileUrl || loanProductsUrl"
               x-show="isEditHop()"
               class="text-xs font-semibold text-gray-600 hover:text-gray-900 whitespace-nowrap">
                {{ __('borrower.apply.cancel') }}
            </a>
            <a :href="loanProductsUrl"
               x-show="! reservationMode && !isEditHop()"
               class="text-xs font-semibold text-gray-600 hover:text-gray-900 whitespace-nowrap">
                {{ __('borrower.apply.details.all_products') }}
            </a>
        </div>
    </div>

    <div class="mt-2 h-1 bg-gray-100 rounded-full overflow-hidden" x-show="!isEditHop() && (phase === 'details' || phase === 'application')" x-cloak>
        <div class="h-full bg-brand transition-all duration-500 rounded-full"
             :style="'width:' + (phase === 'details' ? '35' : Math.min(100, 35 + ((step + 1) / Math.max(steps.length, 1)) * 65)) + '%'"></div>
    </div>
</div>
