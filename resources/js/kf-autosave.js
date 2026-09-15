/**
 * Kopafasta shared autosave — ONE platform primitive for all account shells.
 *
 * Save status belongs to the page shell (top-centre green tab via saving-overlay),
 * never to individual forms. Forms must not render local Saved labels.
 *
 * Reuses Loan Wizard debounce (900ms). Select/radio/date save immediately.
 * Partial valid changes persist — section completeness is independent.
 *
 * States: idle → saving → saved | error(+retry)
 * Saved only after server success. Stale responses cannot overwrite newer values.
 */

const DEFAULT_DEBOUNCE_MS = 900;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
        || '';
}

function labelsFrom(form) {
    return {
        saving: form.getAttribute('data-kf-autosave-saving') || 'Saving…',
        saved: form.getAttribute('data-kf-autosave-saved') || 'Saved',
        fail: form.getAttribute('data-kf-autosave-fail') || 'Not saved',
        retry: form.getAttribute('data-kf-autosave-retry') || 'Retry',
    };
}

function markAccountShellContext() {
    window.kfIsAccountShellContext = function (node) {
        if (typeof window.kfIsBorrowerProfileContext === 'function' && window.kfIsBorrowerProfileContext(node)) {
            return true;
        }
        try {
            const path = String(location.pathname || '');
            if (/\/(borrower\/profile|affiliate|vendor|supplier|investor|partner)\b/.test(path)) {
                return true;
            }
        } catch (e) { /* ignore */ }
        if (node && typeof node.closest === 'function' && node.closest('[data-kf-account-shell],[data-kf-profile-page]')) {
            return true;
        }
        return !! document.querySelector('[data-kf-account-shell],[data-kf-profile-page]');
    };
}

function isSystemFieldName(name) {
    return ['_token', '_method', 'focus', 'wizard', 'return', 'signature_touched'].includes(String(name || ''));
}

function isInstantControl(el) {
    if (!(el instanceof HTMLElement)) return false;
    if (el.matches('select')) return true;
    const type = (el.getAttribute('type') || '').toLowerCase();
    if (['radio', 'checkbox', 'date', 'datetime-local', 'time', 'month'].includes(type)) {
        return true;
    }
    // profile-select / Alpine mirrors persist via named hidden inputs.
    if (type === 'hidden') {
        const name = el.getAttribute('name') || '';
        return name !== '' && ! isSystemFieldName(name);
    }
    return false;
}

/**
 * Bind one form to the shared autosave contract.
 * @returns {{ flush: Function, destroy: Function, state: () => string }}
 */
window.kfBindAutosaveForm = function (form, options = {}) {
    if (!(form instanceof HTMLFormElement) || form.dataset.kfAutosaveBound === '1') {
        return null;
    }
    if (form.hasAttribute('data-no-autosave')) {
        return null;
    }

    form.dataset.kfAutosaveBound = '1';
    form.setAttribute('novalidate', 'novalidate');
    const debounceMs = Number(options.debounceMs ?? form.getAttribute('data-kf-autosave-debounce-ms') ?? DEFAULT_DEBOUNCE_MS) || DEFAULT_DEBOUNCE_MS;
    const labels = { ...labelsFrom(form), ...(options.labels || {}) };

    let timer = null;
    let seq = 0;
    let inflight = 0;
    let state = 'idle'; // idle | saving | saved | error
    let lastError = null;
    let pendingFlush = false;

    function setState(next, errMsg = null) {
        state = next;
        lastError = errMsg;
        form.dataset.kfAutosaveState = state;
        form.dispatchEvent(new CustomEvent('kf-autosave-state', {
            bubbles: true,
            detail: { state, error: lastError },
        }));
    }

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(() => flush(false), debounceMs);
        pendingFlush = true;
    }

    function hasPersistablePayload(fd) {
        for (const [key, value] of fd.entries()) {
            if (['_token', '_method', 'focus', 'wizard', 'return', 'signature_touched'].includes(key)) {
                continue;
            }
            if (typeof File !== 'undefined' && value instanceof File) {
                if (value.size > 0) return true;
                continue;
            }
            if (String(value ?? '').trim() !== '') {
                return true;
            }
        }
        return false;
    }

    async function flush(force = false) {
        clearTimeout(timer);
        pendingFlush = false;
        if (! form.isConnected) return;

        // Signature section: only persist a real drawn signature.
        const focus = String(form.querySelector('input[name="focus"]')?.value || '');
        if (focus === 'signature') {
            const sig = form.querySelector('[name="signature_data"]')?.value || '';
            if (! String(sig).startsWith('data:image/png;base64,')) {
                setState('idle');
                return;
            }
        }

        // Phone widgets keep the real value on a hidden input — sync before FormData.
        form.querySelectorAll('[data-phone-input]').forEach((root) => {
            if (typeof window.syncSitePhoneInput === 'function') {
                window.syncSitePhoneInput(root);
            }
        });

        const fd = new FormData(form);
        if (! fd.get('_token')) {
            fd.append('_token', csrfToken());
        }
        // Partial persistence: do not wait for the whole section to be complete.
        // Still skip empty no-op posts.
        if (! hasPersistablePayload(fd)) {
            setState('idle');
            return;
        }

        const mySeq = ++seq;
        inflight += 1;
        setState('saving');
        if (typeof window.kfShowInlineSaving === 'function') {
            window.kfShowInlineSaving(labels.saving);
        } else if (typeof window.kfShowSaving === 'function') {
            window.kfShowSaving(labels.saving);
        }

        const method = (form.querySelector('input[name=_method]')?.value || form.method || 'POST').toUpperCase();
        const action = form.getAttribute('action') || window.location.href;
        const hasFiles = [...fd.values()].some((v) => typeof File !== 'undefined' && v instanceof File && v.size > 0);
        const uploadLabel = form.getAttribute('data-kf-autosave-uploading') || labels.saving;

        // Let the browser paint Inahifadhi… before the network round-trip resolves.
        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        if (mySeq !== seq) {
            return;
        }

        try {
            const data = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open(method === 'GET' ? 'POST' : 'POST', action);
                xhr.withCredentials = true;
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-KF-Autosave', '1');
                if (hasFiles && xhr.upload && typeof window.kfShowInlineSaving === 'function') {
                    xhr.upload.onprogress = (evt) => {
                        if (! evt.lengthComputable || mySeq !== seq) return;
                        const percent = Math.max(0, Math.min(99, Math.round((evt.loaded / evt.total) * 100)));
                        window.kfShowInlineSaving(uploadLabel, { percent });
                    };
                }
                xhr.onload = () => {
                    let parsed = {};
                    try {
                        parsed = JSON.parse(xhr.responseText || '{}');
                    } catch (e) {
                        parsed = {};
                    }
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve(parsed);
                        return;
                    }
                    const msg = parsed.message
                        || (parsed.errors && Object.values(parsed.errors).flat()[0])
                        || labels.fail;
                    reject(new Error(String(msg)));
                };
                xhr.onerror = () => reject(new Error(labels.fail));
                xhr.send(fd);
            });

            if (mySeq !== seq) {
                return;
            }

            if (data && data.ok === false) {
                throw new Error(String(data.message || labels.fail));
            }

            setState('saved');
            if (typeof window.kfFlashInlineSaved === 'function') {
                window.kfFlashInlineSaved(labels.saved);
            }
            form.dispatchEvent(new CustomEvent('kf-autosave-saved', {
                bubbles: true,
                detail: { data },
            }));
        } catch (e) {
            if (mySeq !== seq) {
                return;
            }
            setState('error', e.message || labels.fail);
            if (typeof window.kfShowSaveError === 'function') {
                window.kfShowSaveError(e.message || labels.fail, labels.retry, () => flush(true));
            } else if (typeof window.kfHideSaving === 'function') {
                window.kfHideSaving();
            }
        } finally {
            inflight = Math.max(0, inflight - 1);
        }
    }

    function onInput(event) {
        const t = event.target;
        if (! (t instanceof HTMLElement)) return;
        if (t.closest('[data-no-autosave]')) return;
        const type = (t.getAttribute('type') || '').toLowerCase();
        if (type === 'password' || type === 'file') return;
        if (t.matches('input[type=hidden]') && isSystemFieldName(t.getAttribute('name'))) {
            return;
        }
        // Selectors / radios / dates: save immediately after a valid change.
        // Defer one microtask so Alpine x-model / :value mirrors commit before FormData.
        if (event.type === 'change' && isInstantControl(t)) {
            clearTimeout(timer);
            pendingFlush = true;
            queueMicrotask(() => flush(true));
            return;
        }
        // Text: debounce after typing stops.
        if (event.type === 'input' || event.type === 'change') {
            schedule();
        }
    }

    function onSubmit(event) {
        if (form.hasAttribute('data-kf-autosave-allow-submit')) return;
        event.preventDefault();
        flush(true);
    }

    function onPageHide() {
        if (pendingFlush || state === 'saving') {
            try {
                flush(true);
            } catch (e) { /* ignore */ }
        }
    }

    form.addEventListener('input', onInput);
    form.addEventListener('change', onInput);
    form.addEventListener('submit', onSubmit);
    window.addEventListener('pagehide', onPageHide);

    // Do not create per-form Saved chips — shell toast is the only indicator.

    const api = {
        flush: () => flush(true),
        destroy() {
            clearTimeout(timer);
            form.removeEventListener('input', onInput);
            form.removeEventListener('change', onInput);
            form.removeEventListener('submit', onSubmit);
            window.removeEventListener('pagehide', onPageHide);
            delete form.dataset.kfAutosaveBound;
            delete form._kfAutosave;
        },
        state: () => state,
    };
    form._kfAutosave = api;
    return api;
};

window.kfBindAllAutosaveForms = function (root = document) {
    root.querySelectorAll('form[data-kf-autosave]').forEach((form) => {
        window.kfBindAutosaveForm(form);
    });
};

window.kfFlushAutosaveForm = function (form) {
    if (!(form instanceof HTMLFormElement)) return;
    window.kfBindAutosaveForm(form);
    form._kfAutosave?.flush?.();
};

export function registerKfAutosave(Alpine) {
    markAccountShellContext();

    const origShowSaving = window.kfShowSaving;
    if (typeof origShowSaving === 'function') {
        window.kfShowSaving = function (message, progress) {
            if (typeof window.kfIsAccountShellContext === 'function' && window.kfIsAccountShellContext()) {
                window.kfShowInlineSaving(message, progress?.percent != null ? { percent: progress.percent } : undefined);
                return;
            }
            return origShowSaving(message, progress);
        };
    }

    Alpine.data('kfAutosaveForm', (config = {}) => ({
        state: 'idle',
        error: null,
        init() {
            const form = this.$el.matches('form') ? this.$el : this.$el.querySelector('form');
            if (! form) return;
            form.setAttribute('data-kf-autosave', '');
            const binding = window.kfBindAutosaveForm(form, {
                debounceMs: config.debounceMs,
                labels: config.labels,
            });
            this._binding = binding;
            this._onState = (e) => {
                this.state = e.detail?.state || 'idle';
                this.error = e.detail?.error || null;
            };
            form.addEventListener('kf-autosave-state', this._onState);
        },
        destroy() {
            this._binding?.destroy?.();
        },
        retry() {
            this._binding?.flush?.();
        },
    }));

    const rebind = () => window.kfBindAllAutosaveForms();
    document.addEventListener('DOMContentLoaded', rebind);
    document.addEventListener('alpine:initialized', () => {
        rebind();
        // Alpine may hydrate card forms after initialized — catch late panels.
        queueMicrotask(rebind);
        setTimeout(rebind, 0);
        setTimeout(rebind, 300);
    });
    document.addEventListener('profile-section-edit', rebind);

    // Bind on first interaction if a form was missed at boot (x-show Edit panels).
    document.addEventListener('focusin', (e) => {
        const form = e.target instanceof Element ? e.target.closest('form[data-kf-autosave]') : null;
        if (form) window.kfBindAutosaveForm(form);
    }, true);

    // Capture-phase bind BEFORE bubble listeners run — Contact email and other text fields
    // were silent when the form was not yet bound at first keystroke.
    if (! window.__kfAutosaveDelegated) {
        window.__kfAutosaveDelegated = true;
        const ensureBound = (e) => {
            const form = e.target instanceof Element ? e.target.closest('form[data-kf-autosave]') : null;
            if (form && ! form.hasAttribute('data-no-autosave')) {
                window.kfBindAutosaveForm(form);
            }
        };
        document.addEventListener('input', ensureBound, true);
        document.addEventListener('change', ensureBound, true);
    }

    // profile-select / address pickers notify here — flush even when change targeting is awkward.
    document.addEventListener('profile-select', (e) => {
        const name = e.detail?.name || '';
        let form = e.target instanceof Element ? e.target.closest('form[data-kf-autosave]') : null;
        if (! form && name) {
            try {
                form = document.querySelector(`form[data-kf-autosave] [name="${CSS.escape(name)}"]`)?.closest('form') || null;
            } catch (err) {
                form = null;
            }
        }
        if (form) {
            window.kfFlushAutosaveForm(form);
        }
    });
}
