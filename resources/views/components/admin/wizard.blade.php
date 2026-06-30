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

<div class="admin-wizard space-y-6">
    <style>
        .admin-wizard:not([data-ready]) [data-step]:not(:first-of-type) { display: none; }
        .admin-wizard:not([data-ready]) [data-wizard-submit] { display: none; }
        .admin-wizard:not([data-ready]) [data-wizard-back] { display: none; }
    </style>

    <nav data-wizard-nav class="hidden flex items-center gap-2 overflow-x-auto pb-1" aria-label="Form steps"></nav>

    <div data-wizard-steps>
        {{ $slot }}
    </div>

    <div class="flex items-center justify-between gap-3 pt-4 border-t border-gray-100">
        @if ($cancelUrl)
            <a href="{{ $cancelUrl }}"
               class="text-sm font-medium text-gray-600 hover:text-gray-800 px-4 py-2">Cancel</a>
        @else
            <span></span>
        @endif

        <div class="flex items-center gap-2">
            <button type="button"
                    data-wizard-back
                    hidden
                    class="text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 ring-1 ring-gray-300 px-4 py-2 rounded-lg transition">
                Back
            </button>

            <button type="button"
                    data-wizard-next
                    class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2 rounded-lg shadow-sm transition">
                Next
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <button type="submit"
                    data-wizard-submit
                    hidden
                    data-submit-label="{{ $submitLabel }}"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 disabled:opacity-70 disabled:cursor-wait px-5 py-2 rounded-lg shadow-sm transition">
                {{ $submitLabel }}
            </button>
        </div>
    </div>
</div>

@once
    <script>
        (function () {
            function pillClass(state) {
                if (state === 'active') {
                    return 'flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border transition bg-amber-600 text-white border-amber-600';
                }
                if (state === 'done') {
                    return 'flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border transition bg-emerald-50 text-emerald-700 border-emerald-300';
                }
                return 'flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border transition bg-white text-gray-600 border-gray-300 hover:border-gray-400';
            }

            function badgeClass(state) {
                if (state === 'active') {
                    return 'size-5 grid place-items-center rounded-full text-[11px] bg-white text-amber-700';
                }
                if (state === 'done') {
                    return 'size-5 grid place-items-center rounded-full text-[11px] bg-emerald-200 text-emerald-800';
                }
                return 'size-5 grid place-items-center rounded-full text-[11px] bg-gray-100 text-gray-600';
            }

            function initWizard(root) {
                if (root.dataset.ready === '1') {
                    return;
                }

                const stepEls = Array.from(root.querySelectorAll('[data-step]'));
                const nav = root.querySelector('[data-wizard-nav]');
                const backBtn = root.querySelector('[data-wizard-back]');
                const nextBtn = root.querySelector('[data-wizard-next]');
                const submitBtn = root.querySelector('[data-wizard-submit]');
                const total = stepEls.length;
                let step = 0;
                const navButtons = [];

                if (total <= 1) {
                    stepEls.forEach(function (el) {
                        el.hidden = false;
                        el.classList.remove('hidden');
                    });
                    if (nextBtn) {
                        nextBtn.hidden = true;
                    }
                    if (submitBtn) {
                        submitBtn.hidden = false;
                    }
                    root.dataset.ready = '1';
                    return;
                }

                nav.classList.remove('hidden');
                nav.classList.add('flex');

                stepEls.forEach(function (el, index) {
                    const label = el.dataset.stepLabel || ('Step ' + (index + 1));
                    const wrap = document.createElement('div');
                    wrap.className = 'flex items-center gap-2 shrink-0';

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = pillClass('upcoming');
                    btn.innerHTML =
                        '<span class="' + badgeClass('upcoming') + '">' + (index + 1) + '</span>' +
                        '<span>' + label + '</span>';
                    btn.addEventListener('click', function () {
                        step = index;
                        render();
                    });
                    wrap.appendChild(btn);
                    navButtons.push({ btn: btn, badge: btn.firstElementChild, label: label, index: index });

                    if (index < total - 1) {
                        const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                        arrow.setAttribute('class', 'size-3 text-gray-300 shrink-0');
                        arrow.setAttribute('fill', 'none');
                        arrow.setAttribute('stroke', 'currentColor');
                        arrow.setAttribute('viewBox', '0 0 24 24');
                        arrow.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>';
                        wrap.appendChild(arrow);
                    }

                    nav.appendChild(wrap);
                });

                const stepsHost = root.querySelector('[data-wizard-steps]');
                const firstInvalid = stepsHost.querySelector('[aria-invalid="true"], .has-error, [data-has-error="true"]');
                if (firstInvalid) {
                    const owner = firstInvalid.closest('[data-step]');
                    if (owner) {
                        step = Math.max(0, stepEls.indexOf(owner));
                    }
                }

                function render() {
                    stepEls.forEach(function (el, index) {
                        const show = index === step;
                        el.hidden = ! show;
                        el.classList.toggle('hidden', ! show);
                    });

                    backBtn.hidden = step === 0;
                    nextBtn.hidden = step >= total - 1;
                    submitBtn.hidden = step !== total - 1;

                    navButtons.forEach(function (item) {
                        let state = 'upcoming';
                        if (item.index < step) {
                            state = 'done';
                        } else if (item.index === step) {
                            state = 'active';
                        }
                        item.btn.className = pillClass(state);
                        if (state === 'done') {
                            item.btn.innerHTML =
                                '<span class="' + badgeClass('done') + '">' +
                                '<svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">' +
                                '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>' +
                                '<span>' + item.label + '</span>';
                        } else {
                            item.btn.innerHTML =
                                '<span class="' + badgeClass(state) + '">' + (item.index + 1) + '</span>' +
                                '<span>' + item.label + '</span>';
                        }
                    });
                }

                nextBtn.addEventListener('click', function () {
                    if (step < total - 1) {
                        step++;
                        render();
                    }
                });

                backBtn.addEventListener('click', function () {
                    if (step > 0) {
                        step--;
                        render();
                    }
                });

                render();
                root.dataset.ready = '1';

                const form = root.closest('form');
                if (form && ! form.dataset.submitGuard) {
                    form.dataset.submitGuard = '1';
                    form.addEventListener('submit', function () {
                        const activeSubmit = root.querySelector('[data-wizard-submit]');
                        [activeSubmit, nextBtn].forEach(function (button) {
                            if (! button || button.hidden) {
                                return;
                            }
                            button.disabled = true;
                            if (button === activeSubmit) {
                                button.textContent = (activeSubmit.dataset.submitLabel || 'Save') + '…';
                            }
                        });
                    });
                }
            }

            function boot() {
                document.querySelectorAll('.admin-wizard').forEach(initWizard);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
    </script>
@endonce
