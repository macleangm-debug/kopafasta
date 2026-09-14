/**
 * Shared “Saving…” feedback for document and multipart form submits.
 *
 * Borrower Profile: never open the blocking fullscreen overlay.
 * Use inline Saving… → ✓ Saved only.
 * Partner/valuation and other flows may still use the modal overlay.
 */
export function registerSavingOverlay(Alpine) {
    Alpine.store('kfSaving', {
        uploading: false,
        message: '',
        current: null,
        total: null,
    });

    window.kfIsBorrowerProfileContext = function (node) {
        try {
            if (/\/borrower\/profile(\/|$)/.test(String(location.pathname || ''))) {
                return true;
            }
        } catch (e) { /* ignore */ }
        if (node && typeof node.closest === 'function' && node.closest('[data-kf-profile-page]')) {
            return true;
        }
        return !! document.querySelector('[data-kf-profile-page]');
    };

    window.kfShowInlineSaving = function (message, progress) {
        let label = message
            || document.querySelector('[data-kf-saving-toast] span:last-child')?.textContent?.trim()
            || 'Saving…';
        const pct = progress?.percent;
        if (typeof pct === 'number' && Number.isFinite(pct) && pct >= 0) {
            const rounded = Math.max(0, Math.min(100, Math.round(pct)));
            // Prefer real byte progress only — never invent a fake %.
            if (! /\d+\s*%/.test(label)) {
                label = `${label.replace(/…\s*$/, '').replace(/\.\.\.\s*$/, '').trim()}… ${rounded}%`;
            } else {
                label = label.replace(/\d+\s*%/, `${rounded}%`);
            }
        }
        // Hide any leftover saved toast while saving.
        document.querySelectorAll('[data-kf-saved-toast]').forEach((el) => {
            el.classList.add('hidden');
            el.style.display = 'none';
        });

        let toast = document.querySelector('[data-kf-saving-toast]');
        if (! toast) {
            toast = document.createElement('div');
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            toast.setAttribute('data-kf-saving-toast', '');
            document.body.appendChild(toast);
        }
        // Inline styles beat Tailwind purge + sit above camera (z-95) and sheets (z-10060).
        toast.className = 'inline-flex items-center gap-2 rounded-full bg-brand text-white shadow-lg px-4 py-2.5 text-sm font-bold';
        toast.style.cssText = 'position:fixed;top:max(1rem,env(safe-area-inset-top));left:50%;transform:translateX(-50%);z-index:10120;display:inline-flex;pointer-events:none;';
        toast.innerHTML = '<span style="width:14px;height:14px;border-radius:9999px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;animation:kf-spin .7s linear infinite" aria-hidden="true"></span><span></span>';
        if (! document.getElementById('kf-inline-save-spin')) {
            const s = document.createElement('style');
            s.id = 'kf-inline-save-spin';
            s.textContent = '@keyframes kf-spin{to{transform:rotate(360deg)}}';
            document.head.appendChild(s);
        }
        toast.querySelector('span:last-child').textContent = label;
        toast.classList.remove('hidden');
        toast.style.display = 'inline-flex';
    };

    window.kfShowSaving = function (message, progress) {
        // Profile / account shells must never use the blocking overlay.
        if (window.kfIsBorrowerProfileContext()
            || (typeof window.kfIsAccountShellContext === 'function' && window.kfIsAccountShellContext())) {
            window.kfShowInlineSaving(message, progress?.percent != null ? { percent: progress.percent } : undefined);
            return;
        }
        Alpine.store('kfSaving').uploading = true;
        Alpine.store('kfSaving').message = message || '';
        Alpine.store('kfSaving').current = progress?.current ?? null;
        Alpine.store('kfSaving').total = progress?.total ?? null;
        if (progress?.percent != null) {
            Alpine.store('kfSaving').percent = progress.percent;
        }
    };

    window.kfUpdateSaving = function (progress) {
        if (window.kfIsBorrowerProfileContext()
            || (typeof window.kfIsAccountShellContext === 'function' && window.kfIsAccountShellContext())) {
            if (progress?.message || progress?.percent != null) {
                window.kfShowInlineSaving(progress.message, progress?.percent != null ? { percent: progress.percent } : undefined);
            }
            return;
        }
        if (! progress) {
            return;
        }
        if (progress.message != null) {
            Alpine.store('kfSaving').message = progress.message;
        }
        if (progress.current != null) {
            Alpine.store('kfSaving').current = progress.current;
        }
        if (progress.total != null) {
            Alpine.store('kfSaving').total = progress.total;
        }
        if (progress.percent != null) {
            Alpine.store('kfSaving').percent = progress.percent;
        }
    };

    window.kfHideSaving = function () {
        Alpine.store('kfSaving').uploading = false;
        Alpine.store('kfSaving').current = null;
        Alpine.store('kfSaving').total = null;
        document.querySelectorAll('[data-kf-saving-toast],[data-kf-save-error-toast]').forEach((el) => {
            el.classList.add('hidden');
            el.style.display = 'none';
        });
    };

    window.kfFlashInlineSaved = function (message) {
        window.kfHideSaving();
        // Hide any error toast.
        document.querySelectorAll('[data-kf-save-error-toast]').forEach((el) => {
            el.classList.add('hidden');
            el.style.display = 'none';
        });
        const label = message || document.querySelector('[data-kf-saved-toast] span:last-child')?.textContent?.trim() || 'Saved';
        let toast = document.querySelector('[data-kf-saved-toast]');
        if (! toast) {
            toast = document.createElement('div');
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            toast.setAttribute('data-kf-saved-toast', '');
            document.body.appendChild(toast);
        }
        toast.className = 'inline-flex items-center gap-2 rounded-full bg-emerald-600 text-white shadow-lg px-4 py-2.5 text-sm font-bold';
        toast.style.cssText = 'position:fixed;top:max(1rem,env(safe-area-inset-top));left:50%;transform:translateX(-50%);z-index:10120;display:inline-flex;pointer-events:none;';
        toast.innerHTML = '<span aria-hidden="true">✓</span><span></span>';
        toast.querySelector('span:last-child').textContent = label;
        toast.classList.remove('hidden');
        toast.style.display = 'inline-flex';
        if (toast._kfSavedTimer) {
            clearTimeout(toast._kfSavedTimer);
        }
        // Brief confirmation only — Complete is the lasting card status.
        toast._kfSavedTimer = setTimeout(() => {
            toast.classList.add('hidden');
            toast.style.display = 'none';
        }, 1800);
    };

    /** Canonical failure toast with Retry — same top-centre position as Saving/Saved. */
    window.kfShowSaveError = function (message, retryLabel, onRetry) {
        window.kfHideSaving();
        document.querySelectorAll('[data-kf-saved-toast]').forEach((el) => {
            el.classList.add('hidden');
            el.style.display = 'none';
        });
        let toast = document.querySelector('[data-kf-save-error-toast]');
        if (! toast) {
            toast = document.createElement('div');
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('data-kf-save-error-toast', '');
            document.body.appendChild(toast);
        }
        toast.className = 'inline-flex items-center gap-2 rounded-full bg-amber-700 text-white shadow-lg px-4 py-2.5 text-sm font-bold';
        toast.style.cssText = 'position:fixed;top:max(1rem,env(safe-area-inset-top));left:50%;transform:translateX(-50%);z-index:10120;display:inline-flex;pointer-events:auto;';
        const fail = message || 'Not saved';
        const retry = retryLabel || 'Retry';
        toast.innerHTML = `<span aria-hidden="true">!</span><span></span><button type="button" data-kf-save-retry class="ml-1 underline font-bold">${retry}</button>`;
        toast.querySelector('span:not([aria-hidden])').textContent = fail;
        toast.classList.remove('hidden');
        toast.style.display = 'inline-flex';
        const btn = toast.querySelector('[data-kf-save-retry]');
        if (btn) {
            btn.onclick = (e) => {
                e.preventDefault();
                toast.classList.add('hidden');
                toast.style.display = 'none';
                if (typeof onRetry === 'function') onRetry();
            };
        }
    };

    window.kfFormNeedsSaving = function (form) {
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-saving')) {
            return false;
        }
        // Generic document holders show upload progress inline — never open the fullscreen overlay.
        if (form.hasAttribute('data-inline-document-progress')) {
            return false;
        }
        // Borrower Profile: never needs the blocking overlay (inline path handles feedback).
        if (window.kfIsBorrowerProfileContext(form)) {
            return false;
        }
        const method = String(form.getAttribute('method') || 'get').toLowerCase();
        if (method === 'get') {
            return false;
        }
        if (form.hasAttribute('data-saving-message') || form.hasAttribute('data-saving')) {
            return true;
        }
        if (String(form.enctype || '').toLowerCase() === 'multipart/form-data') {
            return true;
        }
        return [...form.querySelectorAll('input[type="file"]')].some(
            (input) => input.files && input.files.length > 0,
        );
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const onProfile = window.kfIsBorrowerProfileContext(form);

        if (form.hasAttribute('data-inline-document-progress') || onProfile) {
            // Always keep Profile / inline holders on the non-blocking path.
            const msg = form.getAttribute('data-saving-message')
                || document.querySelector('[data-kf-saving-toast] span:last-child')?.textContent?.trim()
                || '';
            if (msg || String(form.enctype || '').toLowerCase() === 'multipart/form-data'
                || [...form.querySelectorAll('input[type="file"]')].some((input) => input.files && input.files.length > 0)) {
                window.kfShowInlineSaving(msg || undefined);
            }

            const holders = form.querySelectorAll('[data-document-holder]');
            let progressed = false;
            holders.forEach((holder) => {
                const hasFiles = [...holder.querySelectorAll('input[type=file]')].some(
                    (input) => input.files && input.files.length > 0,
                );
                if (! hasFiles) {
                    return;
                }
                progressed = true;
                holder.dispatchEvent(new CustomEvent('kf-inline-document-upload-start', {
                    bubbles: true,
                    detail: {
                        message: form.getAttribute('data-saving-message') || '',
                    },
                }));
            });
            if (! progressed && form.hasAttribute('data-inline-document-progress')) {
                form.dispatchEvent(new CustomEvent('kf-inline-document-upload-start', {
                    bubbles: true,
                    detail: {
                        message: form.getAttribute('data-saving-message') || '',
                    },
                }));
            }
            return;
        }
        if (! window.kfFormNeedsSaving(form)) {
            return;
        }
        window.kfShowSaving(form.getAttribute('data-saving-message') || '');
    }, true);
}
