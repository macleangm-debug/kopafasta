/**
 * Kopafasta shared autosave — ONE platform primitive for all account shells.
 *
 * Reuses Loan Wizard debounce (900ms) + saving-overlay Saving… → ✓ Saved feedback.
 * Forms opt in with data-kf-autosave (and optional data-kf-autosave-debounce-ms).
 * Each surface supplies its own action URL / domain rules — not a shell-specific engine.
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
        fail: form.getAttribute('data-kf-autosave-fail') || 'Could not save',
        retry: form.getAttribute('data-kf-autosave-retry') || 'Retry',
    };
}

function markAccountShellContext() {
    // Partner + borrower shells share inline toast (never fullscreen modal).
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
    const debounceMs = Number(options.debounceMs ?? form.getAttribute('data-kf-autosave-debounce-ms') ?? DEFAULT_DEBOUNCE_MS) || DEFAULT_DEBOUNCE_MS;
    const labels = { ...labelsFrom(form), ...(options.labels || {}) };

    let timer = null;
    let seq = 0;
    let inflight = 0;
    let state = 'idle'; // idle | saving | saved | error
    let lastError = null;
    let pendingFlush = false;

    const statusEl = () => form.querySelector('[data-kf-autosave-status]');

    function renderStatus() {
        const el = statusEl();
        if (! el) return;
        if (state === 'saving') {
            el.innerHTML = `<span class="inline-flex items-center gap-2 text-sm font-semibold text-brand"><span class="size-3.5 rounded-full border-2 border-brand/30 border-t-brand animate-spin" aria-hidden="true"></span>${labels.saving}</span>`;
            el.classList.remove('hidden');
        } else if (state === 'saved') {
            el.innerHTML = `<span class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700">✓ ${labels.saved}</span>`;
            el.classList.remove('hidden');
        } else if (state === 'error') {
            el.innerHTML = `<span class="inline-flex items-center gap-2 text-sm font-semibold text-amber-800">${lastError || labels.fail}</span>
                <button type="button" data-kf-autosave-retry class="text-sm font-bold text-brand hover:underline">${labels.retry}</button>`;
            el.classList.remove('hidden');
            el.querySelector('[data-kf-autosave-retry]')?.addEventListener('click', () => flush(true));
        } else {
            el.classList.add('hidden');
            el.innerHTML = '';
        }
    }

    function setState(next, errMsg = null) {
        state = next;
        lastError = errMsg;
        renderStatus();
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

    async function flush(force = false) {
        clearTimeout(timer);
        pendingFlush = false;
        if (! form.isConnected) return;

        // HTML5 validity — never claim success for invalid incomplete data.
        if (! form.checkValidity()) {
            // Soft: wait until the user completes required fields in this section.
            setState('idle');
            return;
        }

        // Signature section: only persist a real drawn signature (never empty / unrelated).
        const focus = String(form.querySelector('input[name="focus"]')?.value || '');
        if (focus === 'signature') {
            const sig = form.querySelector('[name="signature_data"]')?.value || '';
            if (! String(sig).startsWith('data:image/png;base64,')) {
                setState('idle');
                return;
            }
        }

        const mySeq = ++seq;
        inflight += 1;
        setState('saving');
        if (typeof window.kfShowInlineSaving === 'function') {
            window.kfShowInlineSaving(labels.saving);
        }

        const fd = new FormData(form);
        if (! fd.get('_token')) {
            fd.append('_token', csrfToken());
        }

        const method = (form.querySelector('input[name=_method]')?.value || form.method || 'POST').toUpperCase();
        const action = form.getAttribute('action') || window.location.href;
        const hasFiles = [...fd.values()].some((v) => typeof File !== 'undefined' && v instanceof File && v.size > 0);
        const uploadLabel = form.getAttribute('data-kf-autosave-uploading') || labels.saving;

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

            // Stale guard: ignore older responses.
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
            if (typeof window.kfHideSaving === 'function') {
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
        if (['password', 'file', 'hidden'].includes(type) && type !== 'hidden') return;
        if (type === 'password' || type === 'file') return;
        // Hidden _token/_method changes are ignored; real fields only.
        if (t.matches('input[type=hidden][name=_token], input[type=hidden][name=_method]')) return;
        schedule();
    }

    function onSubmit(event) {
        // Autosave forms should not full-page submit when JS is active.
        if (form.hasAttribute('data-kf-autosave-allow-submit')) return;
        event.preventDefault();
        flush(true);
    }

    function onPageHide() {
        if (pendingFlush || state === 'saving') {
            // Best-effort sync on leave (same idea as Loan Wizard pagehide).
            try {
                flush(true);
            } catch (e) { /* ignore */ }
        }
    }

    form.addEventListener('input', onInput);
    form.addEventListener('change', onInput);
    form.addEventListener('submit', onSubmit);
    window.addEventListener('pagehide', onPageHide);

    // Ensure a status host exists for Retry / inline chip.
    if (! statusEl()) {
        const host = document.createElement('div');
        host.setAttribute('data-kf-autosave-status', '');
        host.className = 'mt-3 hidden';
        form.appendChild(host);
    }

    return {
        flush: () => flush(true),
        destroy() {
            clearTimeout(timer);
            form.removeEventListener('input', onInput);
            form.removeEventListener('change', onInput);
            form.removeEventListener('submit', onSubmit);
            window.removeEventListener('pagehide', onPageHide);
            delete form.dataset.kfAutosaveBound;
        },
        state: () => state,
    };
};

window.kfBindAllAutosaveForms = function (root = document) {
    root.querySelectorAll('form[data-kf-autosave]').forEach((form) => {
        window.kfBindAutosaveForm(form);
    });
};

export function registerKfAutosave(Alpine) {
    markAccountShellContext();

    // Prefer account-shell inline toast over modal when on partner paths.
    const origShowSaving = window.kfShowSaving;
    if (typeof origShowSaving === 'function') {
        window.kfShowSaving = function (message, progress) {
            if (typeof window.kfIsAccountShellContext === 'function' && window.kfIsAccountShellContext()) {
                window.kfShowInlineSaving(message);
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

    document.addEventListener('DOMContentLoaded', () => window.kfBindAllAutosaveForms());
    // Alpine morph / late forms
    document.addEventListener('alpine:initialized', () => window.kfBindAllAutosaveForms());
}
