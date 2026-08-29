/**
 * Inline Screening checklist save — update summary/gates without a full desk reload.
 */
export function bindScreeningChecklistSave() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.id !== 'screening-checklist-form') {
            return;
        }
        event.preventDefault();
        saveChecklist(form, event.submitter instanceof HTMLElement ? event.submitter : null);
    });
}

async function saveChecklist(form, submitter) {
    const buttons = [
        submitter,
        ...form.querySelectorAll('[type="submit"]'),
        document.querySelector('button[form="screening-checklist-form"]'),
    ].filter((el, i, all) => el instanceof HTMLElement && all.indexOf(el) === i);

    buttons.forEach((btn) => {
        btn.disabled = true;
        btn.dataset.kfSaving = '1';
    });

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
            credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => null);
        if (! response.ok || ! payload?.ok) {
            throw new Error(payload?.error || 'Could not save the checklist.');
        }
        applyChecklistSave(payload);
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Could not save the checklist.';
        if (typeof window.showAdminFeedback === 'function') {
            window.showAdminFeedback({ tone: 'error', title: 'Checklist', message });
        }
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
