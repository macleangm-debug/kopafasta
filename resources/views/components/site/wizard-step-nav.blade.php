{{-- Premium step navigation. Expects Alpine parent: steps, step, goto(i) --}}
<div class="sticky top-0 z-20 -mx-4 px-4 py-3 mb-6 bg-[#faf8f5]/95 backdrop-blur-md border-b border-gray-200/70 lg:static lg:mx-0 lg:px-0 lg:py-0 lg:mb-6 lg:border-0 lg:bg-transparent">
    <div class="glass-card p-4 ring-1 ring-brand/10">
        <div class="flex items-center justify-between gap-3 mb-3">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-500">
                {{ __('borrower.apply.wizard_step_progress') }}
            </p>
            <p class="text-xs font-bold text-brand tabular-nums">
                <span x-text="step + 1"></span>/<span x-text="steps.length"></span>
            </p>
        </div>
        <div class="h-2 bg-gray-100 rounded-full overflow-hidden mb-4">
            <div class="h-full bg-brand transition-all duration-500 rounded-full"
                 :style="'width:' + (steps.length ? Math.round(((step + 1) / steps.length) * 100) : 0) + '%'"></div>
        </div>
        <p class="lg:hidden text-sm font-semibold text-gray-900 mb-3" x-text="steps[step]?.label || ''"></p>
        <ol class="flex items-center gap-1.5 overflow-x-auto pb-1 snap-x snap-mandatory scrollbar-none">
            <template x-for="(s, i) in steps" :key="s.key">
                <li class="flex items-center gap-1.5 shrink-0 snap-start">
                    <button type="button"
                            @click="goto(i)"
                            :disabled="i > step"
                            :class="i === step
                                ? 'bg-brand text-white border-brand shadow-sm'
                                : (i < step
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200 cursor-pointer'
                                    : 'bg-white text-gray-400 border-gray-200 cursor-not-allowed opacity-70')"
                            class="size-8 rounded-full grid place-items-center text-xs font-bold border-2 transition"
                            :title="(i + 1) + '. ' + s.label">
                        <template x-if="i < step">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="3"><path d="M5 10l3 3 7-7"/></svg>
                        </template>
                        <template x-if="i >= step">
                            <span x-text="i + 1"></span>
                        </template>
                    </button>
                    <span class="hidden sm:inline text-[11px] font-medium max-w-[6rem] truncate"
                          :class="i === step ? 'text-brand' : (i < step ? 'text-emerald-700' : 'text-gray-400')"
                          :title="s.label"
                          x-text="s.label"></span>
                    <span x-show="i < steps.length - 1" class="text-gray-200 hidden sm:inline">→</span>
                </li>
            </template>
        </ol>
    </div>
</div>
