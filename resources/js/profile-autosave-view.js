/**
 * After a successful Profile autosave, mirror is handled by kfMirrorAutosaveFormToView.
 * Do NOT collapse Edit → View: persistence is not Continue. The open card stays open.
 *
 * Browser only renders the server completion payload — never invents a second %.
 */
export function refreshProfileCompletionUi(detail) {
    const completion = detail?.data?.completion ?? detail?.completion;
    if (! completion || typeof completion !== 'object') {
        return;
    }
    const remaining = Number(completion.section_remaining);
    if (Number.isFinite(remaining)) {
        document.querySelectorAll('[data-kf-section-remaining]').forEach((el) => {
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
    if (completion.percent != null) {
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
    if (Array.isArray(completion.gaps)) {
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
        // Card Complete ticks from the same gap keys (no second client calculator).
        syncCardCompleteFromGaps(keys);
    }
}

function syncCardCompleteFromGaps(gapKeys) {
    if (typeof window.Alpine === 'undefined') {
        return;
    }
    const map = [
        { id: 'profile-about', gap: 'dob' },
        { id: 'profile-family', gap: 'family' },
        { id: 'next-of-kin', gap: 'kin' },
        { id: 'profile-activity', gaps: null }, // activity card uses fields-only; leave Alpine alone unless explicit
    ];
    map.forEach((row) => {
        if (! row.gap) {
            return;
        }
        const el = document.getElementById(row.id);
        if (! el) {
            return;
        }
        try {
            const data = window.Alpine.$data(el);
            if (data && typeof data.complete === 'boolean') {
                data.complete = ! gapKeys.has(row.gap);
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
        // Shared hook for kfAutosave forms and the DOB adapter (same completion payload).
        if (! form.hasAttribute('data-kf-autosave') && ! form.hasAttribute('data-kf-dob-persist')) {
            return;
        }
        if (! form.closest('[id^="profile-"], .glass-card')) {
            return;
        }
        // Keep Alpine open/expanded/showEditAction untouched.
        refreshProfileCompletionUi(event.detail);
    });
}
