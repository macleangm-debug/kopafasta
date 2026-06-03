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
}

export function bindMoneyFormatGlobally() {
    window.KopaFastaFormat = {
        parseNumber,
        formatNumber,
        formatMoney,
        formatTzs,
        initMoneyInputs,
        formatMarkedElements,
    };
    window.formatNumber = formatNumber;
    window.formatMoney = formatMoney;
    window.formatTzs = formatTzs;
    window.initMoneyInputs = initMoneyInputs;

    const boot = () => {
        initMoneyInputs();
        formatMarkedElements();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', boot);
    document.addEventListener('livewire:init', () => {
        if (typeof Livewire !== 'undefined' && Livewire.hook) {
            Livewire.hook('morph.updated', () => boot());
        }
    });
}
