/**
 * Render canonical ProfileCompletionService payload after successful Profile persistence.
 * Do NOT invent a second completion engine. Do NOT collapse cards on ordinary field saves.
 */
export function refreshProfileCompletionUi(detail) {
    const completion = detail?.data?.completion ?? detail?.completion;
    if (completion && typeof completion === 'object') {
        applyOverallPercent(completion);
        applyRemainingList(completion);
        applyCategorySwitcher(completion);
        applyCardCompleteStates(completion);
    }

    applyLiveDocuments(detail);
}

/**
 * After a Profile document JSON save: show the uploaded file in View immediately,
 * keep Add-another when allowed, and flip the parent card off empty.
 */
export function applyLiveDocuments(detail) {
    const docs = detail?.documents
        ?? detail?.data?.documents
        ?? [];
    if (! Array.isArray(docs) || docs.length === 0) {
        return;
    }

    docs.forEach((doc) => {
        if (! doc || typeof doc !== 'object') {
            return;
        }
        paintLiveDocument(doc);
    });

    // Any successful document upload means the card is no longer empty.
    // Keep SSR docs visible; only hide the empty/Add placeholder.
    document.querySelectorAll('[data-kf-doc-view]').forEach((root) => {
        const live = root.querySelector('[data-kf-live-docs]');
        if (! live || live.children.length === 0) {
            return;
        }
        root.querySelectorAll('[data-kf-doc-empty]').forEach((el) => el.classList.add('hidden'));
        live.classList.remove('hidden');
        root.querySelectorAll('[data-kf-doc-add-another]').forEach((el) => el.classList.remove('hidden'));

        const card = root.closest('[id^="profile-"], .glass-card');
        markCardHasDocuments(card);
    });
}

function markCardHasDocuments(card) {
    if (! card || typeof window.Alpine === 'undefined') {
        return;
    }
    try {
        const data = window.Alpine.$data(card);
        if (data && typeof data.empty === 'boolean') {
            data.empty = false;
        }
    } catch (e) {
        // ignore
    }
}

function paintLiveDocument(doc) {
    const code = String(doc.code || doc.field || '');
    if (! code) {
        return;
    }

    document.querySelectorAll('[data-kf-doc-view]').forEach((root) => {
        const codesAttr = (root.getAttribute('data-kf-doc-codes') || '').trim();
        if (! codesAttr) {
            return;
        }
        const allowed = codesAttr.split(/\s+/);
        if (! allowed.includes(code)) {
            return;
        }
        const host = root.querySelector('[data-kf-live-docs]');
        if (! host) {
            return;
        }
        let row = host.querySelector(`[data-kf-live-doc="${cssEscape(code)}"]`);
        if (! row) {
            row = document.createElement('div');
            row.setAttribute('data-kf-live-doc', code);
            row.className = 'rounded-xl ring-1 ring-gray-200 bg-white p-4 space-y-3';
            host.appendChild(row);
        }
        const viewLabel = root.getAttribute('data-kf-doc-view-label') || 'View';
        row.innerHTML = liveDocumentMarkup(doc, viewLabel);
        host.classList.remove('hidden');
    });
}

function liveDocumentMarkup(doc, viewLabel) {
    const labelRaw = String(doc.label || doc.code || '');
    const fileNameRaw = String(doc.file_name || doc.fileName || '');
    const label = escapeHtml(labelRaw);
    const fileName = escapeHtml(fileNameRaw);
    const previewUrl = String(doc.preview_url || doc.previewUrl || '');
    const isPdf = !!(doc.is_pdf ?? doc.isPdf);
    const viewText = escapeHtml(viewLabel || 'View');

    let thumb = '';
    if (previewUrl) {
        if (isPdf) {
            thumb = `<div class="size-14 rounded-xl overflow-hidden bg-brand-muted/40 ring-1 ring-brand/10 shrink-0 grid place-items-center">
                <button type="button" class="size-full flex flex-col items-center justify-center text-brand cursor-zoom-in"
                        onclick="window.kfSiteOpenDocumentPreview(${jsonAttr(previewUrl)}, ${jsonAttr(labelRaw)}, 'pdf')">
                    <span class="text-[10px] font-bold tracking-wide">PDF</span>
                </button>
            </div>`;
        } else {
            thumb = `<div class="size-14 rounded-xl overflow-hidden bg-brand-muted/40 ring-1 ring-brand/10 shrink-0 grid place-items-center">
                <button type="button" class="size-full block cursor-zoom-in"
                        onclick="window.kfSiteOpenDocumentPreview(${jsonAttr(previewUrl)}, ${jsonAttr(labelRaw)}, 'image')">
                    <img src="${escapeHtml(previewUrl)}" alt="" class="size-full object-cover">
                </button>
            </div>`;
        }
    }

    const viewBtn = previewUrl
        ? `<button type="button"
                onclick="window.kfSiteOpenDocumentPreview(${jsonAttr(previewUrl)}, ${jsonAttr(labelRaw)}, ${jsonAttr(isPdf ? 'pdf' : 'image')})"
                class="inline-flex items-center rounded-full bg-brand-gold hover:bg-yellow-400 text-brand px-3 py-1.5 text-xs font-bold shadow-sm">
                ${viewText}
           </button>`
        : '';

    return `<div class="flex items-start gap-3">
        ${thumb}
        <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-gray-900 truncate">${label}</p>
            ${fileName ? `<p class="mt-1 text-xs text-gray-600 truncate" title="${fileName}">${fileName}</p>` : ''}
        </div>
    </div>
    ${viewBtn ? `<div class="mt-3 flex flex-wrap gap-2">${viewBtn}</div>` : ''}`;
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function jsonAttr(value) {
    return JSON.stringify(String(value ?? ''));
}

function cssEscape(value) {
    if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
        return CSS.escape(value);
    }
    return String(value).replace(/"/g, '\\"');
}

function applyOverallPercent(completion) {
    if (completion.percent == null) {
        return;
    }
    const pct = Math.max(0, Math.min(100, Number(completion.percent) || 0));
    const done = pct >= 100;

    document.querySelectorAll('[data-kf-completion-percent]').forEach((el) => {
        const doneLabel = el.getAttribute('data-kf-completion-done-label');
        const template = el.getAttribute('data-percent-template');
        const inHero = !! el.closest('[data-kf-completion-hero]');
        if (done && doneLabel) {
            el.textContent = doneLabel;
            if (inHero) {
                el.classList.remove('bg-white/15', 'text-brand-gold', 'ring-white/25');
                el.classList.add('bg-brand-gold', 'text-brand', 'ring-brand-gold/50');
            }
        } else if (template) {
            el.textContent = template.replace(':percent', String(pct));
            if (inHero) {
                el.classList.add('bg-white/15', 'text-brand-gold', 'ring-white/25');
                el.classList.remove('bg-brand-gold', 'text-brand', 'ring-brand-gold/50');
            }
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

    document.querySelectorAll('[data-kf-completion-hero]').forEach((el) => {
        el.setAttribute('data-kf-completion-done', done ? '1' : '0');
    });

    document.querySelectorAll('[data-kf-completion-cta]').forEach((el) => {
        el.classList.toggle('hidden', done);
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
            // Tick / empty flags only. Never snap Edit → View on autosave — the member
            // stays on the form (business name, employees, etc.) until they close it.
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
