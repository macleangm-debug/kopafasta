/**
 * Inline Screening checklist save — update summary/gates without a full desk reload.
 */
export function bindScreeningChecklistSave() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (! (form instanceof HTMLFormElement) || form.id !== 'screening-checklist-form') {
            return;
        }
        event.preventDefault();
        saveChecklist(form, event.submitter instanceof HTMLElement ? event.submitter : null);
    });
}

function csrfHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
    };
}

function setSaveStatus(message, tone = 'info') {
    const el = document.getElementById('screening-checklist-save-status');
    if (! (el instanceof HTMLElement)) {
        return;
    }
    el.hidden = ! message;
    el.textContent = message || '';
    el.className = 'text-[11px] font-semibold ' + (
        tone === 'error' ? 'text-rose-800' : (tone === 'saving' ? 'text-slate-600' : 'text-emerald-800')
    );
}

function notify(tone, message) {
    if (typeof window.showAdminFeedback === 'function') {
        window.showAdminFeedback({ tone, title: 'Checklist', message });
    }
}

function missingFailReason(form) {
    const radios = form.querySelectorAll('input[type="radio"][name$="[verdict]"][value="fail"]:checked');
    for (const radio of radios) {
        if (! (radio instanceof HTMLInputElement)) {
            continue;
        }
        const reasonName = radio.name.replace(/\[verdict]$/, '[fail_reason_code]');
        const field = form.elements.namedItem(reasonName);
        const value = field instanceof HTMLSelectElement || field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement
            ? String(field.value || '').trim()
            : '';
        if (value === '') {
            return reasonName;
        }
    }

    return null;
}

async function saveChecklist(form, submitter) {
    const missing = missingFailReason(form);
    if (missing) {
        const message = 'Pick a reason for each Concern before saving.';
        setSaveStatus(message, 'error');
        notify('error', message);

        return;
    }

    const buttons = [
        submitter,
        ...form.querySelectorAll('[type="submit"]'),
        document.querySelector('[data-screening-save]'),
        document.querySelector('button[form="screening-checklist-form"]'),
    ].filter((el, i, all) => el instanceof HTMLElement && all.indexOf(el) === i);

    buttons.forEach((btn) => {
        btn.disabled = true;
        btn.dataset.kfSaving = '1';
    });
    setSaveStatus('Saving…', 'saving');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: csrfHeaders(),
            body: new FormData(form),
            credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => null);
        if (! response.ok || ! payload?.ok) {
            throw new Error(payload?.error || (response.status === 419
                ? 'Session expired — refresh and save again.'
                : 'Could not save the checklist.'));
        }
        try {
            applyChecklistSave(payload);
        } catch {
            // Save already succeeded — do not surface a paint error as a failed save.
        }
        setSaveStatus('Saved', 'success');
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Could not save the checklist.';
        setSaveStatus(message, 'error');
        notify('error', message);
    } finally {
        buttons.forEach((btn) => {
            btn.disabled = false;
            delete btn.dataset.kfSaving;
        });
    }
}

function applyChecklistSave(payload) {
    if (payload.readiness_html) {
        const current = document.getElementById('screening-readiness');
        if (current) {
            current.outerHTML = payload.readiness_html;
            const next = document.getElementById('screening-readiness');
            if (next && window.Alpine?.initTree) {
                window.Alpine.initTree(next);
            }
        }
    }

    const desk = payload.desk || {};
    const counts = document.getElementById('screening-desk-counts');
    if (counts && desk.total != null) {
        counts.textContent = `${desk.decided ?? 0}/${desk.total}`;
    }
    const failedEl = document.getElementById('screening-desk-failed');
    if (failedEl) {
        const failed = Number(desk.failed || 0);
        if (failed > 0) {
            failedEl.textContent = `${failed} concern`;
            failedEl.hidden = false;
        } else {
            failedEl.hidden = true;
        }
    }

    Object.entries(payload.gates || {}).forEach(([key, gate]) => {
        const button = document.querySelector(`[data-gate-key="${CSS.escape(key)}"]`);
        if (! (button instanceof HTMLElement)) {
            return;
        }
        const chip = button.querySelector('[data-gate-chip]');
        const status = button.querySelector('[data-gate-status]');
        if (chip) {
            chip.textContent = gate.chip || key;
        }
        if (status) {
            status.textContent = gate.status_label === 'Complete'
                ? 'Complete'
                : (Number(gate.failed || 0) > 0 ? 'Attention' : `${gate.decided ?? 0}/${gate.total ?? 0}`);
        }
        button.disabled = Boolean(gate.locked);
    });
}
