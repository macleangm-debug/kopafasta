/**
 * Profile legal-signature form: hold autosave → holder update without Blade attribute JS.
 * (Nested quotes inside @kf-autosave-saved="..." spilled JS into the page body.)
 */
const DATE_TOKEN = ':date';

function dateLabel(template, date) {
    const base = template || 'Saved :date';
    return base.replaceAll(DATE_TOKEN, date || '—').replaceAll('__DATE__', date || '—');
}

function applySignatureView(form, fields) {
    const url = fields?.legal_signature_data || '';
    if (! url) {
        return;
    }
    const card = form.closest('.glass-card, [id^="profile-"]');
    let holder = card?.querySelector('[data-kf-signature-holder]');
    if (! holder) {
        return;
    }
    holder.classList.remove('hidden');
    if (! holder.querySelector('[data-kf-signature-img]')) {
        holder.innerHTML = [
            '<div class="rounded-2xl bg-gradient-to-br from-brand/5 via-white to-brand-muted/20 ring-1 ring-brand/15 px-4 py-4">',
            '<div class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3 flex items-center justify-center min-h-[6.5rem]">',
            '<img data-kf-signature-img src="" alt="" class="max-h-24 w-full object-contain">',
            '</div>',
            '<p class="text-base font-semibold text-gray-900 mt-3" data-kf-view-field="legal_signer_name"></p>',
            '<p class="text-xs text-gray-500 mt-1" data-kf-signature-date></p>',
            '</div>',
        ].join('');
    }
    const img = holder.querySelector('[data-kf-signature-img]');
    if (img) {
        img.src = url;
    }
    const nameEl = holder.querySelector('[data-kf-view-field="legal_signer_name"]');
    if (nameEl && fields.legal_signer_name) {
        nameEl.textContent = fields.legal_signer_name;
    }
    const dateEl = holder.querySelector('[data-kf-signature-date]');
    if (dateEl && fields.legal_signed_at) {
        const template = form.getAttribute('data-kf-signature-date-template') || '';
        dateEl.textContent = dateLabel(template, fields.legal_signed_at);
    }
    if (card && typeof window.Alpine !== 'undefined') {
        try {
            const data = window.Alpine.$data(card);
            if (data) {
                data.complete = true;
                data.open = false;
                data.expanded = true;
            }
        } catch (e) {
            // Card may not be Alpine-bound yet.
        }
    }
}

function syncPadToHidden(form) {
    const pad = form.querySelector('[data-signature-pad]');
    const alpine = pad && window.Alpine ? window.Alpine.$data(pad) : null;
    if (! alpine) {
        return;
    }
    const hidden = form.querySelector('[name="signature_data"]');
    if (hidden) {
        hidden.value = alpine.dataUrl || '';
    }
}

export function bindProfileSignatureForm(form) {
    if (! (form instanceof HTMLFormElement) || form.dataset.kfSignatureBound === '1') {
        return;
    }
    if (! form.hasAttribute('data-kf-signature-autosave')) {
        return;
    }
    form.dataset.kfSignatureBound = '1';

    form.addEventListener('kf-autosave-saved', (event) => {
        applySignatureView(form, event.detail?.data?.view_fields || {});
    });

    form.addEventListener('submit', () => {
        syncPadToHidden(form);
    });
}

export function bindAllProfileSignatureForms(root = document) {
    root.querySelectorAll('form[data-kf-signature-autosave]').forEach((form) => {
        bindProfileSignatureForm(form);
    });
}

export function registerProfileSignatureBinding() {
    const run = () => bindAllProfileSignatureForms();
    document.addEventListener('DOMContentLoaded', run);
    document.addEventListener('alpine:initialized', () => {
        run();
        queueMicrotask(run);
        setTimeout(run, 0);
        setTimeout(run, 300);
    });
    document.addEventListener('profile-section-edit', run);
    document.addEventListener('focusin', (event) => {
        const form = event.target instanceof Element
            ? event.target.closest('form[data-kf-signature-autosave]')
            : null;
        if (form) {
            bindProfileSignatureForm(form);
        }
    }, true);
}
