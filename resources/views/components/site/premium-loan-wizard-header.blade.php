{{-- Premium wizard header + phase progress. Expects Alpine parent with: phase, current, steps, step --}}
<div class="mb-6">
    <div class="rounded-2xl premium-gradient border border-gray-100/80 px-5 sm:px-7 py-5 sm:py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ brand_name() }} {{ __('borrower.apply.smart_application') }}</p>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-brand">{{ __('borrower.apply.wizard_title') }}</h1>
                <p class="mt-1 text-sm text-gray-600" x-show="current" x-cloak>
                    <span x-text="current?.name"></span>
                    <span class="text-gray-400 mx-1">·</span>
                    <span class="font-mono text-xs" x-text="current?.code"></span>
                </p>
                <p class="mt-1 text-sm text-gray-600" x-show="! current" x-cloak>{{ __('borrower.apply.subtitle') }}</p>
            </div>
            <div class="flex flex-col items-end gap-2 shrink-0">
                <a :href="loanProductsUrl"
                   x-show="! reservationMode"
                   class="text-sm font-semibold text-gray-600 hover:text-gray-900">
                    {{ __('borrower.apply.details.all_products') }}
                </a>
                <div x-show="draftReference" x-cloak
                     class="inline-flex items-center gap-2 rounded-xl bg-white/90 ring-1 ring-brand/15 px-3 py-2 shadow-sm">
                    <span class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.submit_step.reference') }}</span>
                    <span class="font-mono text-xs font-bold text-gray-900" x-text="draftReference"></span>
                </div>
            </div>
        </div>

        <div class="mt-5" x-show="phase === 'details' || phase === 'application'" x-cloak>
            <div class="h-1.5 bg-white/60 rounded-full overflow-hidden">
                <div class="h-full bg-brand transition-all duration-500 rounded-full"
                     :style="'width:' + (phase === 'details' ? '35' : Math.min(100, 35 + ((step + 1) / Math.max(steps.length, 1)) * 65)) + '%'"></div>
            </div>
            <ol class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs font-semibold">
                <li :class="phase === 'details' ? 'text-brand' : 'text-emerald-700'">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-5 rounded-full grid place-items-center text-[10px]"
                              :class="phase === 'details' ? 'bg-brand text-white' : 'bg-emerald-100 text-emerald-800'">1</span>
                        {{ __('borrower.apply.wizard_phases.details') }}
                    </span>
                </li>
                <li class="text-gray-300">→</li>
                <li :class="phase === 'application' ? 'text-brand' : 'text-gray-400'">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-5 rounded-full grid place-items-center text-[10px]"
                              :class="phase === 'application' ? 'bg-brand text-white' : 'bg-gray-100 text-gray-500'">2</span>
                        {{ __('borrower.apply.wizard_phases.application') }}
                    </span>
                </li>
            </ol>
        </div>
    </div>
</div>
