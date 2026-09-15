/**
 * After a successful Profile autosave, mirror is handled by kfMirrorAutosaveFormToView.
 * Do NOT collapse Edit → View: persistence is not Continue. The open card stays open.
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
            // Never auto-open Remaining after saves.
        });
    }
    if (completion.percent != null) {
        document.querySelectorAll('[data-kf-completion-percent]').forEach((el) => {
            const template = el.getAttribute('data-percent-template');
            if (template) {
                el.textContent = template.replace(':percent', String(completion.percent));
            } else {
                el.textContent = String(completion.percent);
            }
        });
        document.querySelectorAll('[data-kf-completion-bar]').forEach((el) => {
            const pct = Math.max(0, Math.min(100, Number(completion.percent) || 0));
            el.style.width = `${pct}%`;
            const bar = el.closest('[role="progressbar"]');
            if (bar) {
                bar.setAttribute('aria-valuenow', String(pct));
            }
        });
    }
    if (Array.isArray(completion.gaps)) {
        document.querySelectorAll('[data-kf-remaining-items]').forEach((list) => {
            const keys = new Set(completion.gaps.map((g) => String(g.key || '')));
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
}

export function registerProfileAutosaveViewCollapse() {
    document.addEventListener('kf-autosave-saved', (event) => {
        const form = event.target;
        if (! (form instanceof HTMLFormElement)) {
            return;
        }
        if (! form.hasAttribute('data-kf-autosave')) {
            return;
        }
        if (! form.closest('[id^="profile-"], .glass-card')) {
            return;
        }
        // Keep Alpine open/expanded/showEditAction untouched.
        refreshProfileCompletionUi(event.detail);
    });
}
