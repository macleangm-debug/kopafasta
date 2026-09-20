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

/** One in-flight autosave at a time across the page — concurrent session writes caused intermittent Unauthenticated. */
let kfAutosaveChain = Promise.resolve();

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
        || '';
}

function readXsrfCookie() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    if (! match) return '';
    try {
        return decodeURIComponent(match[1]);
    } catch (e) {
        return match[1] || '';
    }
}

function syncCsrfIntoForm(form) {
    const token = csrfToken() || '';
    if (! token) return token;
    const meta = document.querySelector('meta[name="csrf-token"]');
    // Prefer cookie-aligned header path; keep form/_token in sync with meta when present.
    form.querySelectorAll('input[name="_token"]').forEach((el) => {
        el.value = token;
    });
    if (meta && token) meta.setAttribute('content', token);
    return token;
}

function labelsFrom(form) {
    return {
        saving: form.getAttribute('data-kf-autosave-saving') || 'Saving…',
        saved: form.getAttribute('data-kf-autosave-saved') || 'Saved',
        fail: form.getAttribute('data-kf-autosave-fail') || 'Not saved',
        retry: form.getAttribute('data-kf-autosave-retry') || 'Retry',
    };
}

function autosaveFailureMessage(raw, parsed, labels) {
    const jsonMessage = parsed && (
        parsed.message
        || (parsed.errors && Object.values(parsed.errors).flat()[0])
    );
    if (typeof window.kfHumanErrorMessage === 'function') {
        return window.kfHumanErrorMessage(jsonMessage, labels.fail);
    }
    const text = String(jsonMessage || '').trim();
    if (text !== '' && ! text.startsWith('<') && ! /<!doctype/i.test(text)) {
        return text;
    }

    return labels.fail;
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
    let coalesceTimer = null;
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
        if (window.__kfDraftRestoring) {
            return;
        }
        clearTimeout(timer);
        clearTimeout(coalesceTimer);
        timer = setTimeout(() => flush(false), debounceMs);
        pendingFlush = true;
    }

    /**
     * Selectors fire change + profile-select (and sometimes input) in one gesture.
     * One coalesced flush avoids seq cancellation + duplicate POSTs that produced
     * intermittent empty 200 / Unauthenticated races while a prior save succeeded.
     */
    function scheduleImmediate() {
        if (window.__kfDraftRestoring) {
            return;
        }
        clearTimeout(timer);
        clearTimeout(coalesceTimer);
        pendingFlush = true;
        coalesceTimer = setTimeout(() => {
            coalesceTimer = null;
            flush(true);
        }, 50);
    }

    function cancelPending() {
        clearTimeout(timer);
        clearTimeout(coalesceTimer);
        coalesceTimer = null;
        timer = null;
        pendingFlush = false;
        if (state === 'saving') {
            // Allow in-flight to finish; bump seq so stale success cannot paint Saved.
            seq += 1;
        } else if (state !== 'saved') {
            setState('idle');
        }
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
        clearTimeout(coalesceTimer);
        pendingFlush = false;
        if (! form.isConnected) return;
        if (window.__kfDraftRestoring) {
            setState('idle');
            return;
        }

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

        syncCsrfIntoForm(form);
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
            inflight = Math.max(0, inflight - 1);
            return;
        }

        const sendOnce = () => new Promise((resolve, reject) => {
            syncCsrfIntoForm(form);
            if (fd.has('_token')) {
                fd.set('_token', csrfToken());
            } else {
                fd.append('_token', csrfToken());
            }
            const xhr = new XMLHttpRequest();
            xhr.open('POST', action);
            xhr.withCredentials = true;
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-KF-Autosave', '1');
            const xsrf = readXsrfCookie();
            if (xsrf) {
                xhr.setRequestHeader('X-XSRF-TOKEN', xsrf);
            }
            const csrf = csrfToken();
            if (csrf) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
            }
            if (hasFiles && xhr.upload && typeof window.kfShowInlineSaving === 'function') {
                xhr.upload.onprogress = (evt) => {
                    if (! evt.lengthComputable || mySeq !== seq) return;
                    const percent = Math.max(0, Math.min(99, Math.round((evt.loaded / evt.total) * 100)));
                    window.kfShowInlineSaving(uploadLabel, { percent });
                };
            }
            xhr.onload = () => {
                let parsed = {};
                const raw = String(xhr.responseText || '').trim();
                try {
                    parsed = raw ? JSON.parse(raw) : {};
                } catch (e) {
                    parsed = {};
                }
                parsed.__httpStatus = xhr.status;
                if (xhr.status >= 200 && xhr.status < 300) {
                    // Empty or non-JSON 200 (aborted race / session bounce) is not a save.
                    if (parsed.ok !== true) {
                        const msg = autosaveFailureMessage(raw, parsed, labels);
                        const err = new Error(String(msg));
                        err.status = xhr.status === 200 && /unauthenticated/i.test(String(msg))
                            ? 401
                            : xhr.status;
                        err.payload = parsed;
                        reject(err);
                        return;
                    }
                    resolve(parsed);
                    return;
                }
                const msg = autosaveFailureMessage(raw, parsed, labels);
                const err = new Error(String(msg));
                err.status = xhr.status;
                err.payload = parsed;
                reject(err);
            };
            xhr.onerror = () => reject(new Error(labels.fail));
            xhr.send(fd);
        });

        const run = async () => {
            try {
                let data = await sendOnce();
                if (mySeq !== seq) return;

                if (data && data.ok === false) {
                    throw new Error(String(data.message || labels.fail));
                }
                if (! data || data.ok !== true) {
                    throw new Error(String((data && data.message) || labels.fail));
                }

                setState('saved');
                if (typeof window.kfFlashInlineSaved === 'function') {
                    window.kfFlashInlineSaved(labels.saved);
                }
                if (typeof window.kfMirrorAutosaveFormToView === 'function') {
                    window.kfMirrorAutosaveFormToView(form, data);
                }
                form.dispatchEvent(new CustomEvent('kf-autosave-saved', {
                    bubbles: true,
                    detail: { data },
                }));
            } catch (e) {
                if (mySeq !== seq) return;
                // One retry on auth/CSRF race (stale meta token vs rotated XSRF cookie).
                if (e && (e.status === 419 || e.status === 401 || e.status === 200)) {
                    try {
                        syncCsrfIntoForm(form);
                        const retry = await sendOnce();
                        if (mySeq !== seq) return;
                        if (! retry || retry.ok !== true) {
                            throw new Error(String((retry && retry.message) || labels.fail));
                        }
                        setState('saved');
                        if (typeof window.kfFlashInlineSaved === 'function') {
                            window.kfFlashInlineSaved(labels.saved);
                        }
                        if (typeof window.kfMirrorAutosaveFormToView === 'function') {
                            window.kfMirrorAutosaveFormToView(form, retry);
                        }
                        return;
                    } catch (retryErr) {
                        e = retryErr;
                    }
                }
                // Keep the real server message (including Unauthenticated.) — do not rename/suppress.
                setState('error', e.message || labels.fail);
                if (typeof window.kfShowSaveError === 'function') {
                    window.kfShowSaveError(e.message || labels.fail, labels.retry, () => flush(true));
                } else if (typeof window.kfHideSaving === 'function') {
                    window.kfHideSaving();
                }
            } finally {
                inflight = Math.max(0, inflight - 1);
            }
        };

        // Serialize across forms so session cookie writes do not race.
        kfAutosaveChain = kfAutosaveChain.then(run, run);
        await kfAutosaveChain;
    }

    function onInput(event) {
        if (window.__kfDraftRestoring) return;
        const t = event.target;
        if (! (t instanceof HTMLElement)) return;
        if (t.closest('[data-no-autosave]')) return;
        const type = (t.getAttribute('type') || '').toLowerCase();
        if (type === 'password' || type === 'file') return;
        if (t.matches('input[type=hidden]') && isSystemFieldName(t.getAttribute('name'))) {
            return;
        }
        // Selectors / radios / dates / profile-select hiddens: one coalesced immediate flush.
        if (event.type === 'change' && isInstantControl(t)) {
            scheduleImmediate();
            return;
        }
        // profile-select also dispatches input on the hidden — do not start a 900ms
        // text debounce that can race the coalesced selector flush.
        if (event.type === 'input' && t.matches('input[type=hidden]') && isInstantControl(t)) {
            scheduleImmediate();
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
        cancelPending,
        destroy() {
            clearTimeout(timer);
            clearTimeout(coalesceTimer);
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

/**
 * After a successful autosave, mirror named field values into the card View panel
 * so Angalia updates without a reload. Prefer server view_fields when present
 * (canonical shared sync for Contact / Family / Kin / Activity / Residence).
 */
window.kfMirrorAutosaveFormToView = function (form, data = null) {
    if (!(form instanceof HTMLFormElement)) return;
    const card = form.closest('.glass-card, [id^="profile-"]');
    if (! card) return;

    const serverFields = data && data.view_fields && typeof data.view_fields === 'object'
        ? data.view_fields
        : null;

    const labels = {
        single: form.getAttribute('data-kf-marital-single') || 'Single',
        married: form.getAttribute('data-kf-marital-married') || 'Married',
        divorced: form.getAttribute('data-kf-marital-divorced') || 'Divorced',
        widowed: form.getAttribute('data-kf-marital-widowed') || 'Widowed',
    };

    let anyFilled = false;
    const fd = new FormData(form);

    const displayFor = (name, raw) => {
        let value = raw == null ? '' : String(raw).trim();
        if (name === 'marital_status' && value) {
            value = labels[value] || value;
        }
        if (name === 'activity_type' && value) {
            try {
                const map = JSON.parse(form.getAttribute('data-kf-activity-type-map') || '{}');
                value = map[value] || value;
            } catch (e) { /* keep raw */ }
        }
        if (name === 'income_range' && value) {
            try {
                const map = JSON.parse(form.getAttribute('data-kf-income-map') || '{}');
                value = map[value] || value;
            } catch (e) { /* keep raw */ }
        }
        return value;
    };

    card.querySelectorAll('[data-kf-view-field]').forEach((el) => {
        const name = el.getAttribute('data-kf-view-field');
        if (! name) return;
        let value;
        if (serverFields && Object.prototype.hasOwnProperty.call(serverFields, name)) {
            value = serverFields[name] == null ? '' : String(serverFields[name]).trim();
        } else {
            let raw = fd.get(name);
            if (raw == null && name.startsWith('activity_details[')) {
                raw = fd.get(name);
            }
            value = displayFor(name, raw);
            if (name === 'nok_relationship' && value && el.getAttribute('data-kf-view-label-map')) {
                try {
                    const map = JSON.parse(el.getAttribute('data-kf-view-label-map') || '{}');
                    value = map[value] || value;
                } catch (e) { /* keep raw */ }
            }
            if (el.getAttribute('data-kf-view-label-map') && value && name !== 'nok_relationship') {
                try {
                    const map = JSON.parse(el.getAttribute('data-kf-view-label-map') || '{}');
                    value = map[value] || value;
                } catch (e) { /* keep raw */ }
            }
        }
        if (value !== '') anyFilled = true;
        el.textContent = value !== '' ? value : '—';
        const row = el.closest('div');
        if (row && value !== '') row.classList.remove('hidden');
    });

    const marital = String(
        (serverFields && serverFields.marital_status_key)
            || fd.get('marital_status')
            || ''
    ).toLowerCase();
    if (marital) {
        const showSpouse = marital === 'married';
        card.querySelectorAll('[data-kf-spouse-row]').forEach((row) => {
            row.classList.toggle('hidden', ! showSpouse);
        });
    }

    if (anyFilled || (serverFields && Object.keys(serverFields).length)) {
        card.querySelectorAll('[data-kf-empty-hint]').forEach((el) => el.classList.add('hidden'));
        card.querySelectorAll('[data-kf-family-view], [data-kf-kin-view], [data-kf-activity-view], [data-kf-view-host]').forEach((el) => {
            el.classList.remove('hidden');
        });
    }
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

    // profile-select / address pickers: sync value, then use the form's coalesced flush
    // (same 50ms window as change/input on the hidden — never a second competing POST).
    document.addEventListener('profile-select', (e) => {
        if (window.__kfDraftRestoring) return;
        const name = e.detail?.name || '';
        const value = e.detail?.value;
        let form = e.target instanceof Element ? e.target.closest('form[data-kf-autosave]') : null;
        if (! form && name) {
            try {
                form = document.querySelector(`form[data-kf-autosave] [name="${CSS.escape(name)}"]`)?.closest('form') || null;
            } catch (err) {
                form = null;
            }
        }
        if (! form) return;
        // Ensure the named control carries the picked value before FormData (Alpine :value races).
        if (name) {
            try {
                const input = form.querySelector(`[name="${CSS.escape(name)}"]`);
                if (input && value != null) {
                    input.value = String(value);
                    input.setAttribute('value', String(value));
                }
            } catch (err) { /* ignore */ }
        }
        window.kfBindAutosaveForm(form);
        // Prefer the bound coalescer; fall back to a direct flush if bind returned early.
        if (form._kfAutosave?.flush) {
            // Trigger the same coalesced path via a synthetic change when possible.
            try {
                const input = name
                    ? form.querySelector(`[name="${CSS.escape(name)}"]`)
                    : null;
                if (input) {
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    return;
                }
            } catch (err) { /* fall through */ }
            form._kfAutosave.flush(true);
        }
    });
}
