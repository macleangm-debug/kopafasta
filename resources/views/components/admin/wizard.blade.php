{{--
    Wizard wrapper. Renders step indicator + Back/Next/Submit controls.
    Auto-detects child <x-admin.step> elements (which carry [data-step][data-step-label]).
    Falls back gracefully to a single-step layout when only one step is present.
    Validation errors auto-jump to the first step containing an invalid field.
--}}
@props([
    'submitLabel' => 'Save',
    'cancelUrl'   => null,
])

<div
    x-data="adminWizard()"
    x-init="init()"
    x-cloak
    class="space-y-6"
>
    {{-- Step indicator (visible only when more than one step) --}}
    <div x-show="total > 1" class="flex items-center gap-2 overflow-x-auto pb-1">
        <template x-for="(label, i) in labels" :key="i">
            <div class="flex items-center gap-2 shrink-0">
                <button type="button"
                        @click="go(i)"
                        :class="i === step
                            ? 'bg-amber-600 text-white border-amber-600'
                            : (i < step
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-300'
                                : 'bg-white text-gray-500 border-gray-300')"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border transition">
                    <span class="size-5 grid place-items-center rounded-full text-[11px]"
                          :class="i === step
                              ? 'bg-white text-amber-700'
                              : (i < step ? 'bg-emerald-200 text-emerald-800' : 'bg-gray-100 text-gray-600')">
                        <span x-show="i >= step" x-text="i + 1"></span>
                        <svg x-show="i < step" x-cloak class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    <span x-text="label"></span>
                </button>
                <svg x-show="i < labels.length - 1" class="size-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </template>
    </div>

    {{-- Step content (only one visible at a time; Alpine toggles via [hidden]) --}}
    <div x-ref="steps">
        {{ $slot }}
    </div>

    {{-- Controls --}}
    <div class="flex items-center justify-between gap-3 pt-4 border-t border-gray-100">
        @if ($cancelUrl)
            <a href="{{ $cancelUrl }}"
               class="text-sm font-medium text-gray-600 hover:text-gray-800 px-4 py-2">Cancel</a>
        @else
            <span></span>
        @endif

        <div class="flex items-center gap-2">
            <button type="button"
                    x-show="step > 0"
                    @click="prev()"
                    class="text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 ring-1 ring-gray-300 px-4 py-2 rounded-lg transition">
                Back
            </button>

            <button type="button"
                    x-show="step < total - 1"
                    @click="next()"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2 rounded-lg shadow-sm transition">
                Next
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <button type="submit"
                    x-show="step === total - 1"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2 rounded-lg shadow-sm transition">
                {{ $submitLabel }}
            </button>
        </div>
    </div>
</div>

@once
    <script>
        window.adminWizard = function () {
            return {
                step: 0,
                total: 0,
                labels: [],
                _stepEls: [],
                init() {
                    this._stepEls = Array.from(this.$refs.steps.querySelectorAll('[data-step]'));
                    this.total = this._stepEls.length;
                    this.labels = this._stepEls.map(el => el.dataset.stepLabel || 'Step');

                    // If the server returned validation errors, jump to the first step that owns an invalid field.
                    const firstInvalid = this.$refs.steps.querySelector('[aria-invalid="true"], .has-error, [data-has-error="true"]');
                    if (firstInvalid) {
                        const owner = firstInvalid.closest('[data-step]');
                        if (owner) {
                            this.step = this._stepEls.indexOf(owner);
                        }
                    }
                    this.render();
                },
                next() { if (this.step < this.total - 1) { this.step++; this.render(); } },
                prev() { if (this.step > 0)              { this.step--; this.render(); } },
                go(i)  { this.step = i; this.render(); },
                render() {
                    this._stepEls.forEach((el, i) => {
                        if (i === this.step) {
                            el.removeAttribute('hidden');
                            el.classList.remove('hidden');
                        } else {
                            el.setAttribute('hidden', 'hidden');
                            el.classList.add('hidden');
                        }
                    });
                },
            };
        };
    </script>
@endonce
