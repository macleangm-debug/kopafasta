{{-- Compact premium wizard chrome. Alpine: phase, current, steps, step, draftReference, assetApplication --}}
<div class="mb-4 rounded-xl kf-premium-panel px-3.5 sm:px-4 py-3 relative overflow-hidden">
    <div class="absolute -right-12 -top-12 h-32 w-32 rounded-full bg-brand-gold/10 pointer-events-none" aria-hidden="true"></div>
    <div class="relative flex flex-wrap items-center gap-x-3 gap-y-2">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <h1 class="text-sm sm:text-base font-bold text-white truncate"
                    x-text="isEditHop()
                        ? (['guarantor'].includes(stepKey) ? @js(__('borrower.apply.change_guarantor')) : @js(__('borrower.apply.submit_step.edit_quote')))
                        : (current?.name || @js(__('borrower.apply.wizard_title')))"></h1>
                <span class="text-[11px] font-mono text-white/60" x-show="!isEditHop() && current?.code" x-cloak x-text="current.code"></span>
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-white/80" x-show="!isEditHop() && assetApplication" x-cloak>
                <span class="font-semibold text-white truncate max-w-[14rem]" x-text="assetApplication.asset_title"></span>
                <span class="text-white/40" x-show="assetApplication.supplier" aria-hidden="true">·</span>
                <span class="truncate max-w-[10rem]" x-show="assetApplication.supplier" x-text="assetApplication.supplier"></span>
                <span class="text-white/40" x-show="assetApplication.remaining_loan" aria-hidden="true">·</span>
                <span class="font-bold tabular-nums whitespace-nowrap text-brand-gold" x-show="assetApplication.remaining_loan" x-text="formatTzs(assetApplication.remaining_loan)"></span>
            </div>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-semibold" x-show="!isEditHop() && (phase === 'details' || phase === 'application')" x-cloak>
                <span class="inline-flex items-center gap-1"
                      :class="phase === 'details' ? 'text-brand-gold' : 'text-emerald-200'">
                    <span class="size-4 rounded-full grid place-items-center text-[9px]"
                          :class="phase === 'details' ? 'bg-brand-gold text-brand' : 'bg-emerald-400/30 text-emerald-100'">1</span>
                    {{ __('borrower.apply.wizard_phases.details') }}
                </span>
                <span class="text-white/35" aria-hidden="true">→</span>
                <span class="inline-flex items-center gap-1"
                      :class="phase === 'application' ? 'text-brand-gold' : 'text-white/50'">
                    <span class="size-4 rounded-full grid place-items-center text-[9px]"
                          :class="phase === 'application' ? 'bg-brand-gold text-brand' : 'bg-white/15 text-white/70'">2</span>
                    {{ __('borrower.apply.wizard_phases.application') }}
                </span>
                <span class="text-white/35" x-show="phase === 'application' && steps?.length" x-cloak>·</span>
                <span class="text-white/70 font-medium tabular-nums" x-show="phase === 'application' && steps?.length" x-cloak
                      x-text="(step + 1) + '/' + steps.length"></span>
            </div>
            <p class="mt-1 text-[11px] text-white/70" x-show="isEditHop()" x-cloak>{{ __('borrower.apply.edit_hop_hint') }}</p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <span x-show="draftReference" x-cloak
                  class="hidden sm:inline-flex items-center gap-1.5 rounded-lg bg-white/10 ring-1 ring-white/15 px-2 py-1 font-mono text-[10px] font-semibold text-white"
                  x-text="draftReference"></span>
            <a :href="profileUrl || loanProductsUrl"
               x-show="isEditHop()"
               class="text-xs font-semibold text-white/80 hover:text-white whitespace-nowrap">
                {{ __('borrower.apply.cancel') }}
            </a>
            <a :href="loanProductsUrl"
               x-show="! reservationMode && !isEditHop()"
               class="text-xs font-semibold text-white/80 hover:text-white whitespace-nowrap">
                {{ __('borrower.apply.details.all_products') }}
            </a>
        </div>
    </div>

    <div class="relative mt-2 h-1 bg-white/15 rounded-full overflow-hidden" x-show="!isEditHop() && (phase === 'details' || phase === 'application')" x-cloak>
        <div class="h-full bg-brand-gold transition-all duration-500 rounded-full"
             :style="'width:' + (phase === 'details' ? '35' : Math.min(100, 35 + ((step + 1) / Math.max(steps.length, 1)) * 65)) + '%'"></div>
    </div>
</div>
