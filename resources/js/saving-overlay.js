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

    window.kfShowInlineSaving = function (message) {
        const label = message
            || document.querySelector('[data-kf-saving-toast] span:last-child')?.textContent?.trim()
            || 'Saving…';
        // Hide any leftover saved toast while saving.
        document.querySelectorAll('[data-kf-saved-toast]').forEach((el) => el.classList.add('hidden'));

        let toast = document.querySelector('[data-kf-saving-toast]');
        if (! toast) {
            toast = document.createElement('div');
            toast.setAttribute('role', 'status');
            toast.setAttribute('data-kf-saving-toast', '');
            document.body.appendChild(toast);
        }
        // Fixed viewport chip so NIDA/face autosave is always visible (no scroll / no Save CTA).
        toast.className = 'fixed top-4 left-1/2 -translate-x-1/2 z-[10070] inline-flex items-center gap-2 rounded-full bg-brand/95 text-white shadow-lg ring-1 ring-white/20 px-4 py-2 text-sm font-bold';
        toast.innerHTML = '<span class="size-3.5 rounded-full border-2 border-white/30 border-t-white animate-spin" aria-hidden="true"></span><span></span>';
        toast.querySelector('span:last-child').textContent = label;
        toast.classList.remove('hidden');
    };

    window.kfShowSaving = function (message, progress) {
        // Profile must never use the blocking “Inahifadhi nyaraka…” modal.
        if (window.kfIsBorrowerProfileContext()) {
            window.kfShowInlineSaving(message);
            return;
        }
        Alpine.store('kfSaving').uploading = true;
        Alpine.store('kfSaving').message = message || '';
        Alpine.store('kfSaving').current = progress?.current ?? null;
        Alpine.store('kfSaving').total = progress?.total ?? null;
    };

    window.kfUpdateSaving = function (progress) {
        if (window.kfIsBorrowerProfileContext()) {
            if (progress?.message) {
                window.kfShowInlineSaving(progress.message);
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
    };

    window.kfHideSaving = function () {
        Alpine.store('kfSaving').uploading = false;
        Alpine.store('kfSaving').current = null;
        Alpine.store('kfSaving').total = null;
        document.querySelectorAll('[data-kf-saving-toast]').forEach((el) => el.classList.add('hidden'));
    };

    window.kfFlashInlineSaved = function (message) {
        window.kfHideSaving();
        const label = message || document.querySelector('[data-kf-saved-toast] span:last-child')?.textContent?.trim() || 'Saved';
        let toast = document.querySelector('[data-kf-saved-toast]');
        if (! toast) {
            toast = document.createElement('div');
            toast.setAttribute('role', 'status');
            toast.setAttribute('data-kf-saved-toast', '');
            document.body.appendChild(toast);
        }
        toast.className = 'fixed top-4 left-1/2 -translate-x-1/2 z-[10070] inline-flex items-center gap-2 rounded-full bg-emerald-600 text-white shadow-lg ring-1 ring-white/20 px-4 py-2 text-sm font-bold';
        toast.innerHTML = '<span aria-hidden="true">✓</span><span></span>';
        toast.querySelector('span:last-child').textContent = label;
        toast.classList.remove('hidden');
        if (toast._kfSavedTimer) {
            clearTimeout(toast._kfSavedTimer);
        }
        toast._kfSavedTimer = setTimeout(() => {
            toast.classList.add('hidden');
        }, 2200);
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
