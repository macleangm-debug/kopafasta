/** Tanzania NIDA: 20 digits → XXXXXXXX-XXXXX-XXXXX-XX */

export function nidaDigits(value) {
    return String(value ?? '').replace(/\D/g, '').slice(0, 20);
}

export function formatNida(value) {
    const digits = nidaDigits(value);

    if (digits.length <= 8) {
        return digits;
    }

    if (digits.length <= 13) {
        return `${digits.slice(0, 8)}-${digits.slice(8)}`;
    }

    if (digits.length <= 18) {
        return `${digits.slice(0, 8)}-${digits.slice(8, 13)}-${digits.slice(13)}`;
    }

    return `${digits.slice(0, 8)}-${digits.slice(8, 13)}-${digits.slice(13, 18)}-${digits.slice(18)}`;
}

function cursorAfterDigitCount(formatted, digitCount) {
    if (digitCount <= 0) {
        return 0;
    }

    let seen = 0;

    for (let i = 0; i < formatted.length; i++) {
        if (/\d/.test(formatted[i])) {
            seen++;
            if (seen >= digitCount) {
                return i + 1;
            }
        }
    }

    return formatted.length;
}

export function bindNidaInput(input) {
    if (!input || input.dataset.nidaBound === '1') {
        return;
    }

    input.dataset.nidaBound = '1';

    const apply = (digitCount = null) => {
        const start = input.selectionStart ?? input.value.length;
        const digitsBefore = digitCount ?? input.value.slice(0, start).replace(/\D/g, '').length;
        const formatted = formatNida(input.value);
        input.value = formatted;
        const pos = cursorAfterDigitCount(formatted, digitsBefore);
        input.setSelectionRange(pos, pos);
    };

    input.addEventListener('keydown', (event) => {
        const allowed = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
        if (allowed.includes(event.key) || event.ctrlKey || event.metaKey) {
            return;
        }

        if (event.key.length === 1 && !/^\d$/.test(event.key)) {
            event.preventDefault();
        }
    });

    input.addEventListener('beforeinput', (event) => {
        if (event.inputType === 'insertText' && event.data && !/^\d+$/.test(event.data)) {
            event.preventDefault();
        }
    });

    input.addEventListener('paste', (event) => {
        event.preventDefault();
        const pasted = (event.clipboardData || window.clipboardData).getData('text') || '';
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? start;
        const merged = input.value.slice(0, start) + pasted + input.value.slice(end);
        input.value = formatNida(merged);
        apply(input.value.replace(/\D/g, '').length);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    input.addEventListener('input', () => apply());

    if (input.value) {
        input.value = formatNida(input.value);
    }
}

export function initNidaInputs(root = document) {
    root.querySelectorAll('[data-nida-input]').forEach(bindNidaInput);
}

export function bindNidaFormatGlobally() {
    window.KopaFastaNida = { nidaDigits, formatNida, initNidaInputs, bindNidaInput };
    window.formatNida = formatNida;

    const boot = () => initNidaInputs();

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
