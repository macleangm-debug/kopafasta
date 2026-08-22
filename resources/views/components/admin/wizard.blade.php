{{--
    Wizard wrapper. Renders a premium step indicator + Back/Next/Submit controls.
    Auto-detects child <x-admin.step> elements (which carry [data-step][data-step-label]).
    Shows one step at a time. Validation errors auto-jump to the first invalid step.
--}}
@props([
    'submitLabel' => 'Save',
    'cancelUrl'   => null,
    'confirmBeforeSubmit' => false,
])

<div class="admin-wizard space-y-6"
     @if ($confirmBeforeSubmit) data-confirm-before-submit="1" @endif>
    <style>
        .admin-wizard:not([data-ready]) [data-step]:not(:first-of-type) { display: none !important; }
        .admin-wizard:not([data-ready]) [data-wizard-submit] { display: none !important; }
        .admin-wizard:not([data-ready]) [data-wizard-back] { display: none !important; }
        .admin-wizard [data-wizard-steps] { position: relative; min-height: 12rem; }
        .admin-wizard [data-step].wizard-step-inactive {
            display: none !important;
        }
        .admin-wizard [data-step]:not(.wizard-step-inactive) {
            animation: adminWizardIn 220ms ease-out;
        }
        @keyframes adminWizardIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <div data-wizard-chrome class="hidden rounded-2xl bg-gradient-to-br from-brand via-brand to-brand-light p-4 sm:p-5 text-white shadow-sm ring-1 ring-brand/20">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-semibold" data-wizard-progress-label>Step 1 of 1</p>
                <h2 class="text-lg sm:text-xl font-bold tracking-tight mt-1 truncate" data-wizard-current-title>Details</h2>
            </div>
            <div class="text-right shrink-0">
                <p class="text-[11px] text-white/70">Complete each section to continue</p>
                <div class="mt-2 h-1.5 w-36 sm:w-48 rounded-full bg-white/15 overflow-hidden">
                    <div data-wizard-progress-bar class="h-full rounded-full bg-brand-gold transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>
        </div>
        <nav data-wizard-nav class="flex items-center gap-0 overflow-x-auto pb-0.5" aria-label="Form steps"></nav>
    </div>

    <div data-wizard-steps>
        {{ $slot }}
    </div>

    <div class="flex items-center justify-between gap-3 pt-4 border-t border-brand/10">
        @if ($cancelUrl)
            <a href="{{ $cancelUrl }}"
               class="text-sm font-medium text-gray-600 hover:text-brand px-4 py-2">Cancel</a>
        @else
            <span></span>
        @endif

        <div class="flex items-center gap-2">
            <button type="button"
                    data-wizard-back
                    hidden
                    class="text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 ring-1 ring-brand/15 px-4 py-2.5 rounded-xl transition">
                Back
            </button>

            <button type="button"
                    data-wizard-next
                    class="inline-flex items-center gap-2 text-sm font-bold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-xl shadow-sm transition">
                Continue
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <button type="{{ $confirmBeforeSubmit ? 'button' : 'submit' }}"
                    data-wizard-submit
                    hidden
                    data-submit-label="{{ $submitLabel }}"
                    class="inline-flex items-center gap-2 text-sm font-bold text-brand bg-brand-gold hover:brightness-95 disabled:opacity-70 disabled:cursor-wait px-5 py-2.5 rounded-xl shadow-sm transition">
                {{ $submitLabel }}
            </button>
        </div>
    </div>
    <p data-wizard-error hidden class="text-sm text-red-700"></p>
</div>

@once
    <script>
        (function () {
            function isShown(el) {
                if (! el) {
                    return true;
                }
                if (el.hasAttribute('x-cloak') || el.hidden) {
                    return false;
                }
                if (el._x_isShown === false) {
                    return false;
                }
                const style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden') {
                    return false;
                }
                return true;
            }

            function fieldIsCheckable(field) {
                if (! field || field.disabled || field.type === 'hidden') {
                    return false;
                }
                if (typeof field.checkVisibility === 'function') {
                    return field.checkVisibility();
                }
                const style = window.getComputedStyle(field);
                if (style.display === 'none' || style.visibility === 'hidden') {
                    return false;
                }
                return field.offsetParent !== null || style.position === 'fixed';
            }

            function visibleSteps(root) {
                return Array.from(root.querySelectorAll('[data-step]')).filter(function (el) {
                    if (el.closest('template')) {
                        return false;
                    }
                    const gate = el.closest('[data-step-gate]');
                    if (gate && ! isShown(gate)) {
                        return false;
                    }
                    return isShown(el);
                });
            }

            function clearStepClip(el) {
                el.hidden = false;
                el.classList.remove('hidden', 'wizard-step-inactive');
                el.removeAttribute('aria-hidden');
                el.style.cssText = '';
            }

            function destroyWizard(root) {
                if (typeof root._wizardCleanup === 'function') {
                    root._wizardCleanup();
                    root._wizardCleanup = null;
                }
                const nav = root.querySelector('[data-wizard-nav]');
                const chrome = root.querySelector('[data-wizard-chrome]');
                if (nav) {
                    nav.innerHTML = '';
                }
                if (chrome) {
                    chrome.classList.add('hidden');
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
                const chrome = root.querySelector('[data-wizard-chrome]');
                const nav = root.querySelector('[data-wizard-nav]');
                const progressLabel = root.querySelector('[data-wizard-progress-label]');
                const currentTitle = root.querySelector('[data-wizard-current-title]');
                const progressBar = root.querySelector('[data-wizard-progress-bar]');
                const backBtn = root.querySelector('[data-wizard-back]');
                const nextBtn = root.querySelector('[data-wizard-next]');
                const submitBtn = root.querySelector('[data-wizard-submit]');
                const errorEl = root.querySelector('[data-wizard-error]');
                const total = stepEls.length;
                let step = 0;
                const navButtons = [];

                function setError(message) {
                    if (! errorEl) {
                        return;
                    }
                    if (! message) {
                        errorEl.hidden = true;
                        errorEl.textContent = '';
                        return;
                    }
                    errorEl.hidden = false;
                    errorEl.textContent = message;
                }

                allSteps.forEach(function (el) {
                    if (! stepEls.includes(el)) {
                        clearStepClip(el);
                    }
                });

                if (total <= 1) {
                    stepEls.forEach(clearStepClip);
                    if (chrome) {
                        chrome.classList.add('hidden');
                    }
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

                if (chrome) {
                    chrome.classList.remove('hidden');
                }

                stepEls.forEach(function (el, index) {
                    const label = el.dataset.stepLabel || ('Step ' + (index + 1));
                    const wrap = document.createElement('div');
                    wrap.className = 'flex items-center shrink-0';

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'group flex flex-col items-center gap-1.5 min-w-[4.5rem] sm:min-w-[5.5rem] px-1';
                    btn.setAttribute('aria-label', label);
                    btn.innerHTML =
                        '<span data-wizard-dot class="size-8 sm:size-9 grid place-items-center rounded-full text-xs font-bold ring-2 ring-white/25 bg-white/10 text-white/80 transition">' +
                        (index + 1) +
                        '</span>' +
                        '<span data-wizard-dot-label class="text-[10px] sm:text-[11px] font-semibold text-white/65 text-center leading-tight max-w-[5.5rem] truncate">' +
                        label +
                        '</span>';
                    btn.addEventListener('click', function () {
                        if (index <= step) {
                            step = index;
                            render();
                        }
                    });
                    wrap.appendChild(btn);
                    navButtons.push({ btn: btn, label: label, index: index });

                    if (index < total - 1) {
                        const line = document.createElement('div');
                        line.setAttribute('data-wizard-connector', '');
                        line.className = 'h-0.5 w-4 sm:w-8 mb-5 rounded-full bg-white/20 transition';
                        wrap.appendChild(line);
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
                const restore = parseInt(root.dataset.restoreStep || '', 10);
                if (! Number.isNaN(restore) && restore >= 0 && restore < total && ! firstInvalid) {
                    step = restore;
                }

                function validateCurrentStep() {
                    setError('');
                    const current = stepEls[step];
                    if (! current) {
                        return true;
                    }
                    const fields = current.querySelectorAll('input, select, textarea');
                    for (const field of fields) {
                        if (! fieldIsCheckable(field)) {
                            continue;
                        }
                        if (typeof field.checkValidity === 'function' && ! field.checkValidity()) {
                            field.reportValidity();
                            if (typeof field.checkVisibility === 'function' && ! field.checkVisibility()) {
                                setError(field.validationMessage || 'Please complete the required fields in this section.');
                            }
                            return false;
                        }
                    }
                    return true;
                }

                function render() {
                    stepEls.forEach(function (el, index) {
                        const show = index === step;
                        el.hidden = false;
                        el.classList.remove('hidden');
                        el.classList.toggle('wizard-step-inactive', ! show);
                        el.setAttribute('aria-hidden', show ? 'false' : 'true');
                        el.style.cssText = '';
                    });

                    backBtn.hidden = step === 0;
                    nextBtn.hidden = step >= total - 1;
                    submitBtn.hidden = step !== total - 1;

                    const label = stepEls[step]?.dataset.stepLabel || ('Step ' + (step + 1));
                    if (progressLabel) {
                        progressLabel.textContent = 'Step ' + (step + 1) + ' of ' + total;
                    }
                    if (currentTitle) {
                        currentTitle.textContent = label;
                    }
                    if (progressBar) {
                        progressBar.style.width = Math.round(((step + 1) / total) * 100) + '%';
                    }

                    navButtons.forEach(function (item) {
                        const dot = item.btn.querySelector('[data-wizard-dot]');
                        const text = item.btn.querySelector('[data-wizard-dot-label]');
                        const connector = item.btn.parentElement.querySelector('[data-wizard-connector]');
                        let state = 'upcoming';
                        if (item.index < step) {
                            state = 'done';
                        } else if (item.index === step) {
                            state = 'active';
                        }

                        if (state === 'active') {
                            dot.className = 'size-8 sm:size-9 grid place-items-center rounded-full text-xs font-bold ring-2 ring-brand-gold bg-brand-gold text-brand shadow transition';
                            dot.textContent = String(item.index + 1);
                            text.className = 'text-[10px] sm:text-[11px] font-bold text-brand-gold text-center leading-tight max-w-[5.5rem] truncate';
                        } else if (state === 'done') {
                            dot.className = 'size-8 sm:size-9 grid place-items-center rounded-full text-xs font-bold ring-2 ring-emerald-300/50 bg-emerald-400 text-emerald-950 shadow-sm transition';
                            dot.innerHTML = '<svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
                            text.className = 'text-[10px] sm:text-[11px] font-semibold text-white/85 text-center leading-tight max-w-[5.5rem] truncate';
                        } else {
                            dot.className = 'size-8 sm:size-9 grid place-items-center rounded-full text-xs font-bold ring-2 ring-white/25 bg-white/10 text-white/70 transition';
                            dot.textContent = String(item.index + 1);
                            text.className = 'text-[10px] sm:text-[11px] font-semibold text-white/55 text-center leading-tight max-w-[5.5rem] truncate';
                        }

                        if (connector) {
                            connector.className = item.index < step
                                ? 'h-0.5 w-4 sm:w-8 mb-5 rounded-full bg-brand-gold transition'
                                : 'h-0.5 w-4 sm:w-8 mb-5 rounded-full bg-white/20 transition';
                        }
                    });
                }

                function onNext() {
                    if (! validateCurrentStep()) {
                        return;
                    }
                    if (step < total - 1) {
                        step++;
                        render();
                        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }

                function onBack() {
                    if (step > 0) {
                        step--;
                        render();
                    }
                }

                function onSubmitClick(event) {
                    if (root.dataset.confirmBeforeSubmit === '1' && submitBtn.type === 'button') {
                        event.preventDefault();
                        if (! validateCurrentStep()) {
                            return;
                        }
                        const form = root.closest('form');
                        if (! form) {
                            setError('Could not find the form to submit.');
                            return;
                        }
                        window.dispatchEvent(new CustomEvent('admin-wizard-confirm-submit', {
                            detail: { form: form, wizard: root },
                        }));
                        return;
                    }
                }

                nextBtn.addEventListener('click', onNext);
                backBtn.addEventListener('click', onBack);
                submitBtn.addEventListener('click', onSubmitClick);

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
                    submitBtn.removeEventListener('click', onSubmitClick);
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
