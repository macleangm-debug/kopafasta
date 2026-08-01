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
                    class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-xl shadow-sm transition">
                Next
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <button type="submit"
                    data-wizard-submit
                    hidden
                    data-submit-label="{{ $submitLabel }}"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 disabled:opacity-70 disabled:cursor-wait px-5 py-2.5 rounded-xl shadow-sm transition">
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
                    return 'flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border transition bg-brand text-white border-brand';
                }
                if (state === 'done') {
                    return 'flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border transition bg-brand-muted text-brand border-brand/25';
                }
                return 'flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border transition bg-white text-gray-600 border-brand/15 hover:border-brand/30';
            }

            function badgeClass(state) {
                if (state === 'active') {
                    return 'size-5 grid place-items-center rounded-full text-[11px] bg-brand-gold text-brand';
                }
                if (state === 'done') {
                    return 'size-5 grid place-items-center rounded-full text-[11px] bg-brand/20 text-brand';
                }
                return 'size-5 grid place-items-center rounded-full text-[11px] bg-gray-100 text-gray-600';
            }

            function clearStepClip(el) {
                el.hidden = false;
                el.classList.remove('hidden', 'wizard-step-inactive');
                el.removeAttribute('aria-hidden');
                el.style.cssText = '';
            }

            function visibleSteps(root) {
                return Array.from(root.querySelectorAll('[data-step]')).filter(function (el) {
                    const gate = el.closest('[data-step-gate]');
                    if (! gate) {
                        return true;
                    }
                    return window.getComputedStyle(gate).display !== 'none';
                });
            }

            function destroyWizard(root) {
                if (typeof root._wizardCleanup === 'function') {
                    root._wizardCleanup();
                    root._wizardCleanup = null;
                }
                const nav = root.querySelector('[data-wizard-nav]');
                if (nav) {
                    nav.innerHTML = '';
                    nav.classList.add('hidden');
                    nav.classList.remove('flex');
                }
                root.querySelectorAll('[data-step]').forEach(clearStepClip);
                root.dataset.ready = '0';
            }

            function initWizard(root) {
                if (root.dataset.ready === '1') {
                    return;
                }

                const allSteps = Array.from(root.querySelectorAll('[data-step]'));
                const stepEls = visibleSteps(root);
                const nav = root.querySelector('[data-wizard-nav]');
                const backBtn = root.querySelector('[data-wizard-back]');
                const nextBtn = root.querySelector('[data-wizard-next]');
                const submitBtn = root.querySelector('[data-wizard-submit]');
                const total = stepEls.length;
                let step = 0;
                const navButtons = [];

                // Gated-away steps stay under Alpine x-show; clear wizard clips on them.
                allSteps.forEach(function (el) {
                    if (! stepEls.includes(el)) {
                        clearStepClip(el);
                    }
                });

                if (total <= 1) {
                    stepEls.forEach(clearStepClip);
                    if (nextBtn) {
                        nextBtn.hidden = true;
                    }
                    if (backBtn) {
                        backBtn.hidden = true;
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
                    navButtons.push({ btn: btn, label: label, index: index });

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
                    if (owner && stepEls.includes(owner)) {
                        step = Math.max(0, stepEls.indexOf(owner));
                    }
                }

                function render() {
                    stepEls.forEach(function (el, index) {
                        const show = index === step;
                        el.hidden = false;
                        el.classList.remove('hidden');
                        el.classList.toggle('wizard-step-inactive', ! show);
                        el.setAttribute('aria-hidden', show ? 'false' : 'true');
                        el.style.cssText = show
                            ? ''
                            : 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;';
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

                function onNext() {
                    if (step < total - 1) {
                        step++;
                        render();
                    }
                }

                function onBack() {
                    if (step > 0) {
                        step--;
                        render();
                    }
                }

                nextBtn.addEventListener('click', onNext);
                backBtn.addEventListener('click', onBack);

                function onInvalid(event) {
                    const target = event.target;
                    if (! (target instanceof HTMLElement)) {
                        return;
                    }
                    const owner = target.closest('[data-step]');
                    if (! owner || ! stepEls.includes(owner)) {
                        return;
                    }
                    const index = stepEls.indexOf(owner);
                    if (index >= 0 && index !== step) {
                        step = index;
                        render();
                    }
                }

                function onSubmit() {
                    stepEls.forEach(clearStepClip);
                    const activeSubmit = root.querySelector('[data-wizard-submit]');
                    [activeSubmit, nextBtn, backBtn].forEach(function (button) {
                        if (! button) {
                            return;
                        }
                        button.disabled = true;
                        button.hidden = button !== activeSubmit;
                        if (button === activeSubmit) {
                            activeSubmit.hidden = false;
                            const label = activeSubmit.dataset.submitLabel || 'Save';
                            activeSubmit.innerHTML =
                                '<svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
                                '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                                '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>' +
                                '</svg>' +
                                '<span>' + label + '…</span>';
                        }
                    });
                }

                const form = root.closest('form');
                if (form) {
                    form.addEventListener('invalid', onInvalid, true);
                    form.addEventListener('submit', onSubmit);
                }

                root._wizardCleanup = function () {
                    nextBtn.removeEventListener('click', onNext);
                    backBtn.removeEventListener('click', onBack);
                    if (form) {
                        form.removeEventListener('invalid', onInvalid, true);
                        form.removeEventListener('submit', onSubmit);
                    }
                };

                render();
                root.dataset.ready = '1';
            }

            function boot() {
                document.querySelectorAll('.admin-wizard').forEach(initWizard);
            }

            function rebuildAll() {
                document.querySelectorAll('.admin-wizard').forEach(function (root) {
                    destroyWizard(root);
                    initWizard(root);
                });
            }

            window.addEventListener('admin-wizard-rebuild', function () {
                // Wait a tick so Alpine x-show display styles are applied.
                setTimeout(rebuildAll, 0);
            });

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
    </script>
@endonce
