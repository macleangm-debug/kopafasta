/**
 * Keep in-progress form values across refresh (sessionStorage).
 * Skips secrets, files, and forms marked data-no-draft.
 */
const STORAGE_PREFIX = 'kf-form-draft:';
const MAX_AGE_MS = 24 * 60 * 60 * 1000;
const SKIP_NAME = /^(?:_token|_method|password|password_confirmation|current_password|pin|pin_confirmation|activation_pin|otp|cvv|card_number|secret|api_key|api_secret|webhook_secret|sms_api_key|sms_api_secret|email_smtp_pass|email_smtp_user)$/i;


let restoring = false;

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
    // Integration credential forms must always hydrate from the server, never session drafts.
    if (form.hasAttribute('data-integration-settings-form') || form.closest('[data-integration-settings]')) {
        return true;
    }
    const method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'post') {
        return true;
    }
    const action = (form.getAttribute('action') || '').toLowerCase();
    if (
        action.includes('logout')
        || action.includes('login')
        || action.includes('/locale')
        || action.endsWith('/country')
        || action.includes('/country?')
        || action.includes('/setup-pin')
        || action.includes('/settings/payin')
        || action.includes('/settings/crb')
        || action.includes('/settings/gateways')
        || action.includes('/settings/integrations')
    ) {
        return true;
    }

    return false;
}

function shouldSkipField(field) {
    if (! field || ! field.name) {
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

function alpineData(el) {
    while (el) {
        if (el._x_dataStack?.[0]) {
            return el._x_dataStack[0];
        }
        el = el.parentElement;
    }

    return null;
}

function setSimpleAlpineModel(field, value) {
    const model = field.getAttribute('x-model');
    if (! model || ! /^[A-Za-z_$][\w$]*$/.test(model)) {
        return;
    }
    const data = alpineData(field);
    if (! data) {
        return;
    }
    try {
        data[model] = value;
    } catch (error) {
        // Ignore read-only Alpine getters.
    }
}

function gateInFlow(gate) {
    if (! gate) {
        return true;
    }
    if (gate.hasAttribute('x-cloak') || gate.hidden) {
        return false;
    }
    if (gate._x_isShown === false) {
        return false;
    }
    const style = window.getComputedStyle(gate);
    if (style.display === 'none' || style.visibility === 'hidden') {
        return false;
    }

    return true;
}

function wizardStepsInFlow(wizard) {
    return [...wizard.querySelectorAll('[data-step]')].filter((el) => {
        if (el.closest('template')) {
            return false;
        }

        return gateInFlow(el.closest('[data-step-gate]'));
    });
}

function currentWizardState(wizard) {
    const steps = wizardStepsInFlow(wizard);
    const current = steps.find((el) => ! el.classList.contains('wizard-step-inactive'))
        || steps.find((el) => el.getAttribute('aria-hidden') === 'false')
        || steps[0];

    return {
        wizardStep: current ? Math.max(0, steps.indexOf(current)) : 0,
        wizardStepLabel: current?.dataset.stepLabel || '',
    };
}

function collect(form) {
    const values = {};
    form.querySelectorAll('input, select, textarea').forEach((field) => {
        if (shouldSkipField(field) || field.disabled) {
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
    const wizardState = wizard ? currentWizardState(wizard) : { wizardStep: 0, wizardStepLabel: '' };

    return { values, ...wizardState, savedAt: Date.now() };
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
            setSimpleAlpineModel(fields.find((field) => field.checked) || fields[0], value);
            return;
        }
        const field = fields[0];
        field.value = value ?? '';
        setSimpleAlpineModel(field, value ?? '');
        field.dispatchEvent(new Event('input', { bubbles: true }));
        if (! field.hasAttribute('x-model') && field.tagName !== 'SELECT') {
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
}

function syncPhoneWidgets(form) {
    form.querySelectorAll('[data-phone-input]').forEach((wrap) => {
        const hidden = wrap.querySelector('[data-phone-hidden], input[type="hidden"][name]');
        const data = wrap._x_dataStack?.[0];
        if (! hidden || ! data || typeof data.local === 'undefined') {
            return;
        }
        const prefixDigits = String(data.prefix || '').replace(/\D/g, '');
        let local = String(hidden.value || '').replace(/\D/g, '');
        if (prefixDigits && local.startsWith(prefixDigits)) {
            local = local.slice(prefixDigits.length);
        }
        data.local = local.replace(/^0+/, '');
        if (typeof data.syncHidden === 'function') {
            data.syncHidden();
        }
    });
}

function syncAddressWidgets(form, values) {
    form.querySelectorAll('[x-data]').forEach((el) => {
        const data = el._x_dataStack?.[0];
        if (! data || typeof data.refreshDistricts !== 'function' || typeof data.region !== 'string') {
            return;
        }
        const regionField = el.querySelector('select[name]');
        const districtField = el.querySelector('input[type="hidden"][name]');
        if (regionField && Object.prototype.hasOwnProperty.call(values, regionField.name)) {
            data.region = values[regionField.name] || '';
        }
        if (districtField && Object.prototype.hasOwnProperty.call(values, districtField.name)) {
            data.savedDistrict = values[districtField.name] || '';
            data.district = values[districtField.name] || '';
        }
        data.refreshDistricts();
        if (typeof data.syncDistrictSelection === 'function') {
            data.syncDistrictSelection();
        }
    });
}

function applyDraft(form, payload) {
    applyValues(form, payload.values);
    syncPhoneWidgets(form);
    syncAddressWidgets(form, payload.values || {});

    const wizard = form.querySelector('.admin-wizard');
    if (! wizard) {
        return;
    }
    const steps = wizardStepsInFlow(wizard);
    let index = Number.isInteger(payload.wizardStep) ? payload.wizardStep : 0;
    if (payload.wizardStepLabel) {
        const byLabel = steps.findIndex((el) => el.dataset.stepLabel === payload.wizardStepLabel);
        if (byLabel >= 0) {
            index = byLabel;
        }
    }
    wizard.dataset.restoreStep = String(Math.max(0, index));
    if (payload.wizardStepLabel) {
        wizard.dataset.restoreStepLabel = payload.wizardStepLabel;
    }
    window.dispatchEvent(new CustomEvent('admin-wizard-rebuild'));
}

function hasServerErrors(form) {
    return Boolean(form.querySelector('[data-has-error="true"], [data-server-errors]'));
}

function save(form) {
    if (restoring || shouldSkipForm(form)) {
        return;
    }
    try {
        sessionStorage.setItem(formKey(form), JSON.stringify(collect(form)));
    } catch (error) {
        // Quota / private mode — ignore.
    }
}

function restore(form) {
    if (shouldSkipForm(form) || hasServerErrors(form) || form.dataset.draftRestored === '1') {
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

    form.dataset.draftRestored = '1';
    restoring = true;
    applyDraft(form, payload);

    const replay = () => {
        applyValues(form, payload.values);
        syncPhoneWidgets(form);
        syncAddressWidgets(form, payload.values || {});
        const wizard = form.querySelector('.admin-wizard');
        if (wizard && payload.wizardStepLabel) {
            wizard.dataset.restoreStepLabel = payload.wizardStepLabel;
        }
        if (wizard && Number.isInteger(payload.wizardStep)) {
            wizard.dataset.restoreStep = String(payload.wizardStep);
        }
    };

    requestAnimationFrame(() => {
        replay();
        setTimeout(() => {
            replay();
            restoring = false;
            const wizard = form.querySelector('.admin-wizard');
            if (wizard) {
                delete wizard.dataset.restoreStep;
                delete wizard.dataset.restoreStepLabel;
            }
        }, 250);
    });
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

    window.addEventListener('pagehide', () => {
        document.querySelectorAll('form').forEach((form) => save(form));
    });

    const boot = () => {
        document.querySelectorAll('form').forEach((form) => restore(form));
    };

    document.addEventListener('alpine:initialized', () => setTimeout(boot, 50));
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => setTimeout(boot, 350));
    } else {
        setTimeout(boot, 350);
    }
}
