/**
 * System-wide numeric display: 1000 → 1,000 · 1500000.5 → 1,500,000.50
 */
export function parseNumber(raw) {
    if (raw === null || raw === undefined || raw === '') {
        return 0;
    }
    if (typeof raw === 'number') {
        return Number.isFinite(raw) ? raw : 0;
    }
    const cleaned = String(raw).replace(/[^\d.-]/g, '');
    if (cleaned === '' || cleaned === '-' || cleaned === '.') {
        return 0;
    }
    const n = parseFloat(cleaned);

    return Number.isFinite(n) ? n : 0;
}

export function formatNumber(value, decimals = 0) {
    const n = parseNumber(value);

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(n);
}

export function formatMoney(value, options = {}) {
    const currency = options.currency ?? 'TZS';
    const decimals = options.decimals ?? 0;
    const withCode = options.withCode ?? true;

    const formatted = formatNumber(value, decimals);

    return withCode ? `${currency} ${formatted}` : formatted;
}

/** @deprecated Use formatMoney — kept for existing Alpine/inline scripts */
export function formatTzs(value, decimals = 0) {
    return formatMoney(value, { currency: 'TZS', decimals });
}

export function initMoneyInputs(root = document) {
    root.querySelectorAll('[data-money-input]').forEach((input) => {
        if (input.dataset.moneyBound === '1') {
            return;
        }
        input.dataset.moneyBound = '1';
        const decimals = parseInt(input.dataset.moneyInput || '0', 10);

        const format = (raw) => {
            const cleaned = String(raw).replace(/[^\d.]/g, '');
            if (cleaned === '') {
                return '';
            }
            const parts = cleaned.split('.');
            const whole = parts[0].replace(/^0+(?=\d)/, '') || '0';
            const withCommas = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            if (decimals > 0 && parts.length > 1) {
                return withCommas + '.' + parts[1].slice(0, decimals);
            }

            return withCommas;
        };

        input.addEventListener('input', () => {
            const pos = input.selectionStart;
            const before = input.value.length;
            input.value = format(input.value);
            const after = input.value.length;
            const next = Math.max(0, (pos ?? after) + (after - before));
            input.setSelectionRange(next, next);
        });

        input.addEventListener('blur', () => {
            input.value = format(input.value);
        });

        if (input.value) {
            input.value = format(input.value);
        }
    });
}

export function formatMarkedElements(root = document) {
    root.querySelectorAll('[data-format-number]').forEach((el) => {
        const decimals = parseInt(el.dataset.formatDecimals ?? el.dataset.decimals ?? '0', 10);
        const raw = el.dataset.formatNumber ?? el.textContent;
        el.textContent = formatNumber(raw, decimals);
    });

    root.querySelectorAll('[data-format-money]').forEach((el) => {
        const decimals = parseInt(el.dataset.formatDecimals ?? el.dataset.decimals ?? '0', 10);
        const currency = el.dataset.formatCurrency ?? 'TZS';
        const raw = el.dataset.formatMoney ?? el.textContent;
        el.textContent = formatMoney(raw, { currency, decimals });
    });

    root.querySelectorAll('[data-auto-format="money"]').forEach((el) => {
        if (el.dataset.autoFormatted === '1') return;
        const raw = el.dataset.value ?? el.textContent;
        const decimals = parseInt(el.dataset.decimals ?? '0', 10);
        const currency = el.dataset.currency ?? 'TZS';
        el.textContent = formatMoney(raw, { currency, decimals });
        el.dataset.autoFormatted = '1';
        el.classList.add('tabular-nums');
    });

    root.querySelectorAll('[data-auto-format="number"]').forEach((el) => {
        if (el.dataset.autoFormatted === '1') return;
        const raw = el.dataset.value ?? el.textContent;
        const decimals = parseInt(el.dataset.decimals ?? '0', 10);
        el.textContent = formatNumber(raw, decimals);
        el.dataset.autoFormatted = '1';
        el.classList.add('tabular-nums');
    });
}

/** Format bare numeric text in elements marked for client-side display. */
export function formatNumericTextNodes(root = document) {
    root.querySelectorAll('.fmt-num, .fmt-money').forEach((el) => {
        if (el.dataset.autoFormatted === '1' || el.children.length > 0) return;
        const text = (el.textContent || '').trim();
        if (! /^-?\d+(\.\d+)?$/.test(text.replace(/,/g, ''))) return;
        const decimals = (text.split('.')[1] || '').length;
        const isMoney = el.classList.contains('fmt-money');
        el.textContent = isMoney
            ? formatMoney(text, { decimals })
            : formatNumber(text, decimals);
        el.dataset.autoFormatted = '1';
    });
}

export function bindMoneyFormatGlobally() {
    window.KopaFastaFormat = {
        parseNumber,
        formatNumber,
        formatMoney,
        formatTzs,
        initMoneyInputs,
        formatMarkedElements,
        formatNumericTextNodes,
    };
    window.formatNumber = formatNumber;
    window.formatMoney = formatMoney;
    window.formatTzs = formatTzs;
    window.initMoneyInputs = initMoneyInputs;

    const boot = () => {
        initMoneyInputs();
        formatMarkedElements();
        formatNumericTextNodes();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (! (form instanceof HTMLFormElement)) {
            return;
        }
        form.querySelectorAll('[data-money-input]').forEach((input) => {
            if (input instanceof HTMLInputElement && input.value) {
                input.value = String(parseNumber(input.value));
            }
        });
    }, true);

    document.addEventListener('livewire:navigated', boot);
    document.addEventListener('livewire:init', () => {
        if (typeof Livewire !== 'undefined' && Livewire.hook) {
            Livewire.hook('morph.updated', () => boot());
        }
    });

    let moneyInputTimer = 0;
    new MutationObserver(() => {
        window.clearTimeout(moneyInputTimer);
        moneyInputTimer = window.setTimeout(() => initMoneyInputs(), 50);
    }).observe(document.documentElement, { childList: true, subtree: true });
}
