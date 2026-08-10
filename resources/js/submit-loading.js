/**
 * Global submit / busy-button feedback.
 * On form submit: disable the submitter, dim it, show a spinner + "…" label.
 * Prevents double submits while the browser navigates or the request runs.
 *
 * Opt out: form[data-skip-loading="1"] or form[data-submit-guard="1"]
 *          (or Alpine x-data that already owns a busy/submitting/… flag)
 * Custom label: button[data-loading-label="Paying…"]
 *
 * For non-form buttons (fetch / XHR):
 *   kfMarkBusy(btn) / kfClearBusy(btn)
 *   or mark the button data-loading="1" and call kfMarkBusy from your handler.
 */
const BUSY_KEYS = ['paying', 'submitting', 'applying', 'uploading', 'saving', 'busy', 'loading'];

const BUSY_CLASSES = ['opacity-70', 'cursor-wait', 'inline-flex', 'items-center', 'justify-center', 'gap-2', 'pointer-events-none'];

function spinnerHtml(label) {
    return '<svg class="size-4 animate-spin shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
        '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
        '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>' +
        '</svg><span>' + label + '</span>';
}

function loadingLabelFor(el) {
    const raw = (el.dataset.loadingLabel
        || el.getAttribute('data-submit-label')
        || (el.tagName === 'INPUT' ? el.value : el.textContent)
        || 'Working').trim().replace(/\s+/g, ' ');

    return /…$|\.\.\.$/.test(raw) ? raw : (raw + '…');
}

export function kfClearBusy(el) {
    if (!(el instanceof HTMLElement)) {
        return;
    }

    if (el.dataset.originalHtml != null) {
        el.innerHTML = el.dataset.originalHtml;
        delete el.dataset.originalHtml;
    } else if (el.dataset.originalValue != null) {
        el.value = el.dataset.originalValue;
        delete el.dataset.originalValue;
    }

    el.disabled = false;
    el.removeAttribute('aria-busy');
    delete el.dataset.kfBusy;
    el.classList.remove(...BUSY_CLASSES);
}

export function kfMarkBusy(el, label) {
    if (!(el instanceof HTMLElement) || el.dataset.kfBusy === '1' || el.disabled) {
        return;
    }

    el.dataset.kfBusy = '1';
    el.setAttribute('aria-busy', 'true');

    const loadingLabel = label || loadingLabelFor(el);

    if (el.tagName === 'BUTTON') {
        el.dataset.originalHtml = el.innerHTML;
        el.innerHTML = spinnerHtml(loadingLabel);
    } else if (el.tagName === 'INPUT') {
        el.dataset.originalValue = el.value;
        el.value = loadingLabel;
    }

    el.disabled = true;
    el.classList.add(...BUSY_CLASSES);
}

function resetFormLoading(form) {
    if (!(form instanceof HTMLFormElement) || form.dataset.loadingBound !== '1') {
        return;
    }

    delete form.dataset.loadingBound;
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
        if (btn.dataset.kfBusy === '1') {
            kfClearBusy(btn);
        } else {
            btn.disabled = false;
        }
    });
}

function resetAlpineBusyFlags() {
    document.querySelectorAll('[x-data]').forEach((el) => {
        const stack = el._x_dataStack;
        if (! Array.isArray(stack)) {
            return;
        }

        stack.forEach((data) => {
            if (! data || typeof data !== 'object') {
                return;
            }

            BUSY_KEYS.forEach((key) => {
                if (Object.prototype.hasOwnProperty.call(data, key) && data[key] === true) {
                    data[key] = false;
                }
            });
        });
    });
}

function resetAllLoadingUi() {
    document.querySelectorAll('form[data-loading-bound="1"]').forEach(resetFormLoading);
    document.querySelectorAll('[data-kf-busy="1"]').forEach(kfClearBusy);
    resetAlpineBusyFlags();
}

function shouldSkipForm(form) {
    if (form.dataset.skipLoading === '1' || form.dataset.submitGuard === '1') {
        return true;
    }

    if (form.dataset.loadingBound === '1') {
        return true;
    }

    // Admin multi-step wizards manage their own CTA state.
    if (form.querySelector('.admin-wizard')) {
        return true;
    }

    const xData = form.getAttribute('x-data') || '';
    if (xData && new RegExp('\\b(' + BUSY_KEYS.join('|') + ')\\b').test(xData)) {
        return true;
    }

    return false;
}

function onSubmit(event) {
    // Bubble phase so @submit.prevent (confirm modals) can cancel first.
    if (event.defaultPrevented) {
        return;
    }

    const form = event.target;
    if (!(form instanceof HTMLFormElement) || shouldSkipForm(form)) {
        return;
    }

    const submitter = event.submitter instanceof HTMLButtonElement || event.submitter instanceof HTMLInputElement
        ? event.submitter
        : form.querySelector('button[type="submit"], input[type="submit"]');

    if (! submitter || submitter.disabled) {
        return;
    }

    form.dataset.loadingBound = '1';
    kfMarkBusy(submitter);

    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
        if (btn !== submitter) {
            btn.disabled = true;
        }
    });
}

/**
 * Optional: buttons outside forms that navigate or kick off work.
 * Use data-loading="click" on <button type="button"> or <a> that should
 * show busy state immediately on click (until next page or kfClearBusy).
 */
function onClick(event) {
    const target = event.target instanceof Element
        ? event.target.closest('[data-loading="click"]')
        : null;

    if (! target || target.dataset.kfBusy === '1') {
        return;
    }

    // Let confirm / Alpine preventDefault handlers win.
    if (event.defaultPrevented) {
        return;
    }

    kfMarkBusy(target);
}

export function bindSubmitLoading() {
    if (typeof window === 'undefined' || window.__kfSubmitLoadingBound) {
        return;
    }

    window.__kfSubmitLoadingBound = true;
    window.kfMarkBusy = kfMarkBusy;
    window.kfClearBusy = kfClearBusy;

    document.addEventListener('submit', onSubmit);
    document.addEventListener('click', onClick);
    window.addEventListener('pageshow', resetAllLoadingUi);
}
