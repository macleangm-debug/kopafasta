/**
 * After a successful Profile autosave, collapse Edit → View on the card.
 * Complements kfMirrorAutosaveFormToView without touching kf-autosave.js.
 */
function collapseCardToView(form) {
    const card = form.closest('.glass-card, [id^="profile-"]');
    if (! card || typeof window.Alpine === 'undefined') {
        return;
    }
    try {
        const data = window.Alpine.$data(card);
        if (! data) {
            return;
        }
        data.complete = true;
        data.open = false;
        data.expanded = true;
        data.showEditAction = false;
    } catch (e) {
        // Card may not be Alpine-bound yet.
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
        // Only Profile accordion cards — not unrelated autosave forms.
        if (! form.closest('[id^="profile-"], .glass-card')) {
            return;
        }
        collapseCardToView(form);
    });
}
