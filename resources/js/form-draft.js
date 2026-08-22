/**
 * Keep in-progress form values across refresh (sessionStorage).
 * Skips secrets, files, and forms marked data-no-draft.
 */
const STORAGE_PREFIX = 'kf-form-draft:';
const MAX_AGE_MS = 24 * 60 * 60 * 1000;
const SKIP_NAME = /^(?:_token|_method|password|password_confirmation|current_password|pin|pin_confirmation|activation_pin|otp|cvv|card_number|secret)$/i;

function formKey(form) {
    const action = form.getAttribute('action') || '';
    const id = form.id || '';

    return STORAGE_PREFIX + location.pathname + location.search + '#' + (id || action || 'form');
}

function shouldSkipForm(form) {
    if (! (form instanceof HTMLFormElement)) {
        return true;
    }
    if (form.hasAttribute('data-no-draft') || form.closest('[data-no-draft]')) {
        return true;
    }
    const method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'post') {
        return true;
    }
    const action = (form.getAttribute('action') || '').toLowerCase();
    if (action.includes('logout') || action.includes('login')) {
        return true;
    }

    return false;
}

function shouldSkipField(field) {
    if (! field || ! field.name || field.disabled) {
        return true;
    }
    if (field.type === 'password' || field.type === 'file') {
        return true;
    }
    if (SKIP_NAME.test(field.name) || /\[password]|\[pin]|\[secret]/i.test(field.name)) {
        return true;
    }

    return false;
}

function collect(form) {
    const values = {};
    form.querySelectorAll('input, select, textarea').forEach((field) => {
        if (shouldSkipField(field)) {
            return;
        }
        if (field.type === 'checkbox') {
            if (! Array.isArray(values[field.name])) {
                values[field.name] = [];
            }
            if (field.checked) {
                values[field.name].push(field.value || '1');
            }
            return;
        }
        if (field.type === 'radio') {
            if (field.checked) {
                values[field.name] = field.value;
            }
            return;
        }
        values[field.name] = field.value;
    });

    const wizard = form.querySelector('.admin-wizard');
    let wizardStep = 0;
    if (wizard) {
        const all = [...wizard.querySelectorAll('[data-step]')].filter((el) => ! el.closest('template'));
        const current = all.find((el) => ! el.classList.contains('wizard-step-inactive'));
        wizardStep = current ? Math.max(0, all.indexOf(current)) : 0;
    }

    return { values, wizardStep, savedAt: Date.now() };
}

function applyValues(form, values) {
    Object.entries(values || {}).forEach(([name, value]) => {
        const fields = [...form.querySelectorAll(`[name="${CSS.escape(name)}"]`)];
        if (fields.length === 0) {
            return;
        }
        if (fields[0].type === 'checkbox') {
            const selected = Array.isArray(value) ? value : [value];
            fields.forEach((field) => {
                field.checked = selected.includes(field.value) || (field.value === '1' && selected.includes('1'));
            });
            return;
        }
        if (fields[0].type === 'radio') {
            fields.forEach((field) => {
                field.checked = field.value === value;
            });
            return;
        }
        fields[0].value = value ?? '';
        fields[0].dispatchEvent(new Event('input', { bubbles: true }));
        fields[0].dispatchEvent(new Event('change', { bubbles: true }));
    });
}

function hasServerErrors(form) {
    return Boolean(
        form.querySelector('[data-has-error="true"]')
        || form.previousElementSibling?.classList?.contains('bg-red-50')
        || form.parentElement?.querySelector?.('.text-red-700')
    );
}

function save(form) {
    if (shouldSkipForm(form)) {
        return;
    }
    try {
        sessionStorage.setItem(formKey(form), JSON.stringify(collect(form)));
    } catch (error) {
        // Quota / private mode — ignore.
    }
}

function restore(form) {
    if (shouldSkipForm(form) || hasServerErrors(form)) {
        return;
    }
    let raw;
    try {
        raw = sessionStorage.getItem(formKey(form));
    } catch (error) {
        return;
    }
    if (! raw) {
        return;
    }
    let payload;
    try {
        payload = JSON.parse(raw);
    } catch (error) {
        return;
    }
    if (! payload || (Date.now() - (payload.savedAt || 0)) > MAX_AGE_MS) {
        sessionStorage.removeItem(formKey(form));
        return;
    }
    applyValues(form, payload.values);
    const wizard = form.querySelector('.admin-wizard');
    if (wizard && Number.isInteger(payload.wizardStep)) {
        wizard.dataset.restoreStep = String(payload.wizardStep);
        window.dispatchEvent(new CustomEvent('admin-wizard-rebuild'));
    }
}

function clear(form) {
    try {
        sessionStorage.removeItem(formKey(form));
    } catch (error) {
        // ignore
    }
}

export function bindFormDrafts() {
    const persist = (event) => {
        const form = event.target?.closest?.('form');
        if (form) {
            save(form);
        }
    };

    document.addEventListener('input', persist, true);
    document.addEventListener('change', persist, true);
    document.addEventListener('click', (event) => {
        if (event.target?.closest?.('[data-wizard-next], [data-wizard-back], [data-wizard-submit]')) {
            const form = event.target.closest('form');
            if (form) {
                setTimeout(() => save(form), 50);
            }
        }
    }, true);

    document.addEventListener('submit', (event) => {
        if (event.target instanceof HTMLFormElement) {
            clear(event.target);
        }
    }, true);

    const boot = () => {
        document.querySelectorAll('form').forEach((form) => restore(form));
    };

    const scheduleBoot = () => setTimeout(boot, 80);

    document.addEventListener('alpine:initialized', scheduleBoot);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => setTimeout(boot, 280));
    } else {
        setTimeout(boot, 280);
    }
}
