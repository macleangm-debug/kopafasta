/**
 * Render canonical ProfileCompletionService payload after successful Profile persistence.
 * Do NOT invent a second completion engine. Do NOT collapse cards on ordinary field saves.
 */
export function refreshProfileCompletionUi(detail) {
    const completion = detail?.data?.completion ?? detail?.completion;
    if (! completion || typeof completion !== 'object') {
        return;
    }

    applyOverallPercent(completion);
    applyRemainingList(completion);
    applyCategorySwitcher(completion);
    applyCardCompleteStates(completion);
}

function applyOverallPercent(completion) {
    if (completion.percent == null) {
        return;
    }
    const pct = Math.max(0, Math.min(100, Number(completion.percent) || 0));
    document.querySelectorAll('[data-kf-completion-percent]').forEach((el) => {
        const template = el.getAttribute('data-percent-template');
        if (template) {
            el.textContent = template.replace(':percent', String(pct));
        } else {
            el.textContent = String(pct);
        }
    });
    document.querySelectorAll('[data-kf-completion-bar]').forEach((el) => {
        el.style.width = `${pct}%`;
        const bar = el.closest('[role="progressbar"]');
        if (bar) {
            bar.setAttribute('aria-valuenow', String(pct));
        }
    });
}

function applyRemainingList(completion) {
    const remaining = Number(completion.section_remaining);
    if (Number.isFinite(remaining)) {
        document.querySelectorAll('[data-kf-section-remaining]').forEach((el) => {
            // Active-category badges that also act as category status are handled in applyCategorySwitcher.
            if (el.hasAttribute('data-kf-category-status')) {
                return;
            }
            el.setAttribute('data-count', String(remaining));
            if (remaining <= 0) {
                el.classList.add('hidden');
            } else {
                el.classList.remove('hidden');
                const label = el.getAttribute('data-label-template') || ':count';
                el.textContent = label.replace(':count', String(remaining));
            }
        });
        document.querySelectorAll('[data-kf-remaining-list]').forEach((list) => {
            if (remaining <= 0) {
                list.classList.add('hidden');
            }
        });
    }

    if (completion.section_done != null && completion.section_total != null) {
        document.querySelectorAll('[data-kf-section-progress]').forEach((el) => {
            const template = el.getAttribute('data-progress-template');
            if (template) {
                el.textContent = template
                    .replace(':done', String(completion.section_done))
                    .replace(':total', String(completion.section_total));
            }
        });
    }

    if (! Array.isArray(completion.gaps)) {
        return;
    }
    const keys = new Set(completion.gaps.map((g) => String(g.key || '')));
    document.querySelectorAll('[data-kf-remaining-items]').forEach((list) => {
        list.querySelectorAll('[data-kf-remaining-key]').forEach((row) => {
            const key = row.getAttribute('data-kf-remaining-key') || '';
            const li = row.closest('li') || row;
            if (! keys.has(key)) {
                li.remove();
            }
        });
        if (! list.querySelector('[data-kf-remaining-key]')) {
            const wrap = list.closest('[data-kf-remaining-list]');
            if (wrap) {
                wrap.classList.add('hidden');
            }
        }
    });
}

function applyCategorySwitcher(completion) {
    const categories = completion.categories;
    if (! categories || typeof categories !== 'object') {
        return;
    }

    Object.entries(categories).forEach(([key, state]) => {
        const remaining = Number(state?.remaining ?? 0);
        const complete = !! state?.complete;
        document.querySelectorAll(`[data-kf-category="${key}"]`).forEach((row) => {
            const dot = row.querySelector('[data-kf-category-dot]');
            if (dot) {
                dot.classList.toggle('bg-emerald-500', complete);
                dot.classList.toggle('bg-amber-400', ! complete);
            }
            row.querySelectorAll('[data-kf-category-status]').forEach((status) => {
                if (key === 'assets' && ! complete) {
                    status.classList.add('hidden');
                    return;
                }
                status.classList.remove('hidden');
                renderCategoryStatus(status, complete, remaining);
            });
        });
    });

    // Active trigger badge (outside the dropdown rows).
    const activeKey = completion.section || '';
    if (activeKey && categories[activeKey]) {
        const remaining = Number(categories[activeKey].remaining ?? 0);
        const complete = !! categories[activeKey].complete;
        document.querySelectorAll('[data-kf-active-category-status]').forEach((status) => {
            renderCategoryStatus(status, complete, remaining);
        });
    }
}

function renderCategoryStatus(status, complete, remaining) {
    const completeLabel = status.getAttribute('data-complete-label') || 'Complete';
    const remainingTemplate = status.getAttribute('data-remaining-template') || ':count';
    status.classList.remove('hidden', 'text-emerald-700', 'text-amber-700');
    if (complete || remaining <= 0) {
        status.textContent = completeLabel;
        status.classList.add('text-emerald-700');
        status.setAttribute('data-count', '0');
    } else {
        status.textContent = remainingTemplate.replace(':count', String(remaining));
        status.classList.add('text-amber-700');
        status.setAttribute('data-count', String(remaining));
    }
}

function applyCardCompleteStates(completion) {
    const cards = completion.cards;
    if (! cards || typeof cards !== 'object' || typeof window.Alpine === 'undefined') {
        return;
    }

    Object.entries(cards).forEach(([id, complete]) => {
        const el = document.getElementById(id);
        if (! el) {
            return;
        }
        try {
            const data = window.Alpine.$data(el);
            if (! data || typeof data.complete !== 'boolean') {
                return;
            }
            const nowComplete = !! complete;
            data.complete = nowComplete;
            // Final required field saved while editing: leave Edit → View with Complete tick.
            // Do not fully collapse the card (multi-field editing stays open until complete).
            if (nowComplete && data.open) {
                data.open = false;
                data.expanded = true;
                data.showEditAction = false;
            }
            if (nowComplete) {
                data.empty = false;
            }
        } catch (e) {
            // ignore
        }
    });
}

export function registerProfileAutosaveViewCollapse() {
    document.addEventListener('kf-autosave-saved', (event) => {
        const form = event.target;
        if (! (form instanceof HTMLFormElement)) {
            return;
        }
        // Shared hook for kfAutosave, DOB adapter, and signature autosave forms.
        if (
            ! form.hasAttribute('data-kf-autosave')
            && ! form.hasAttribute('data-kf-dob-persist')
            && ! form.hasAttribute('data-kf-signature-autosave')
        ) {
            return;
        }
        if (! form.closest('[id^="profile-"], .glass-card')) {
            return;
        }
        refreshProfileCompletionUi(event.detail);
    });
}
