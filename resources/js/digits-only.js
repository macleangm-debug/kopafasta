/**
 * Strip non-digits from phone / numeric entry fields across the platform.
 * Opt-in via [data-digits-only], and harden common tel inputs.
 */
export function bindDigitsOnlyGlobally() {
    const matches = (el) => {
        if (!(el instanceof HTMLInputElement)) return false;
        if (el.dataset.digitsOnly !== undefined) return true;
        if (el.type === 'tel') return true;
        return false;
    };

    const sanitize = (el) => {
        if (! matches(el) || el.dataset.digitsSanitize === '0') return;
        const allowSpaces = el.dataset.digitsAllowSpaces === '1';
        const cleaned = allowSpaces
            ? String(el.value || '').replace(/[^\d\s]/g, '')
            : String(el.value || '').replace(/\D/g, '');
        if (el.value === cleaned) return;
        el.value = cleaned;
        el.dispatchEvent(new Event('input', { bubbles: true }));
    };

    document.addEventListener('input', (event) => sanitize(event.target), true);
    document.addEventListener('paste', (event) => {
        const el = event.target;
        if (! matches(el)) return;
        requestAnimationFrame(() => sanitize(el));
    }, true);
}
