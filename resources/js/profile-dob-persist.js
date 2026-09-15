/**
 * Thin DOB persistence for About Me only.
 *
 * Teleported date Confirm cannot reliably drive frozen kfAutosave without
 * special-casing the engine. This adapter posts the existing Profile personal
 * endpoint and reuses the canonical Saving/Saved toast UI — nothing else.
 *
 * Do not grow this into a second autosave framework.
 */
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
        || '';
}

function labelsFrom(form) {
    return {
        saving: form.getAttribute('data-kf-dob-saving') || 'Saving…',
        saved: form.getAttribute('data-kf-dob-saved') || 'Saved',
        fail: form.getAttribute('data-kf-dob-fail') || 'Could not save',
    };
}

function findDobForm(event) {
    const fromTarget = event.target instanceof Element
        ? event.target.closest('form[data-kf-dob-persist]')
        : null;
    if (fromTarget) {
        return fromTarget;
    }

    return document.querySelector('form[data-kf-dob-persist]');
}

function applyDobView(form, viewFields) {
    const card = form.closest('.glass-card, #profile-about');
    if (! card) {
        return;
    }
    const fields = viewFields && typeof viewFields === 'object' ? viewFields : {};
    card.querySelectorAll('[data-kf-view-field]').forEach((el) => {
        const key = el.getAttribute('data-kf-view-field');
        if (! key || ! Object.prototype.hasOwnProperty.call(fields, key)) {
            return;
        }
        const value = fields[key] == null ? '' : String(fields[key]).trim();
        el.textContent = value !== '' ? value : '—';
    });
    if (typeof window.Alpine !== 'undefined') {
        try {
            const data = window.Alpine.$data(card);
            if (data) {
                data.complete = true;
                data.open = false;
                data.expanded = true;
                data.showEditAction = false;
            }
        } catch (e) {
            // ignore
        }
    }
}

function showDobError(form, message) {
    const host = form.querySelector('[data-kf-dob-error]') || form.querySelector('.max-w-sm');
    if (! host) {
        return;
    }
    let el = form.querySelector('[data-kf-dob-error]');
    if (! el) {
        el = document.createElement('p');
        el.setAttribute('data-kf-dob-error', '');
        el.className = 'text-xs text-red-600 mt-1';
        host.appendChild(el);
    }
    el.textContent = message || labelsFrom(form).fail;
}

function clearDobError(form) {
    form.querySelector('[data-kf-dob-error]')?.remove();
}

async function persistDob(form, value) {
    if (form.dataset.kfDobBusy === '1') {
        return;
    }
    const iso = String(value || '').trim();
    if (! iso) {
        return;
    }

    const hidden = form.querySelector('input[name="date_of_birth"]');
    if (hidden) {
        hidden.value = iso;
        hidden.setAttribute('value', iso);
    }

    const labels = labelsFrom(form);
    form.dataset.kfDobBusy = '1';
    clearDobError(form);

    if (typeof window.kfShowInlineSaving === 'function') {
        window.kfShowInlineSaving(labels.saving);
    }

    const body = new FormData();
    body.append('_token', csrfToken());
    body.append('_method', 'PUT');
    body.append('focus', 'about');
    body.append('date_of_birth', iso);

    try {
        const res = await fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-KF-Autosave': '1',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body,
        });
        const data = await res.json().catch(() => ({}));

        if (! res.ok || data.ok === false || data.saved === false) {
            const message = data.message
                || data.errors?.date_of_birth?.[0]
                || data.errors?.national_id?.[0]
                || labels.fail;
            showDobError(form, message);
            if (typeof window.kfShowSaveError === 'function') {
                window.kfShowSaveError(message, null, null);
            } else if (typeof window.kfHideSaving === 'function') {
                window.kfHideSaving();
            }
            return;
        }

        applyDobView(form, data.view_fields || {});
        if (typeof window.kfFlashInlineSaved === 'function') {
            window.kfFlashInlineSaved(labels.saved);
        } else if (typeof window.kfHideSaving === 'function') {
            window.kfHideSaving();
        }
    } catch (e) {
        showDobError(form, labels.fail);
        if (typeof window.kfShowSaveError === 'function') {
            window.kfShowSaveError(labels.fail, null, null);
        } else if (typeof window.kfHideSaving === 'function') {
            window.kfHideSaving();
        }
    } finally {
        delete form.dataset.kfDobBusy;
    }
}

export function registerProfileDobPersist() {
    document.addEventListener('kf-date-changed', (event) => {
        if (event.detail?.name !== 'date_of_birth') {
            return;
        }
        const form = findDobForm(event);
        if (! form) {
            return;
        }
        persistDob(form, event.detail?.value);
    });
}
