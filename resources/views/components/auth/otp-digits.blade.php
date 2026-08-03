@props([
    'name' => 'code',
    'length' => 6,
    'autofocus' => false,
    'label' => null,
])

@php
    $length = max(4, min(8, (int) $length));
@endphp

<div
    data-otp-digits
    data-otp-length="{{ $length }}"
    {{ $attributes->class('w-full') }}
>
    @if ($label)
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-2">{{ $label }}</label>
    @endif
    <div class="flex justify-between gap-2 sm:gap-2.5" role="group" aria-label="{{ $label ?? 'One-time code' }}">
        @for ($i = 0; $i < $length; $i++)
            <input
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="1"
                autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                data-otp-index="{{ $i }}"
                @if ($autofocus && $i === 0) autofocus @endif
                aria-label="Digit {{ $i + 1 }}"
                class="size-11 sm:size-12 text-center text-lg font-bold tabular-nums rounded-xl border-0 ring-1 ring-gray-200 bg-white text-gray-900 shadow-sm focus:ring-2 focus:ring-brand focus:outline-none"
            >
        @endfor
    </div>
    <input type="hidden" name="{{ $name }}" data-otp-value value="{{ old($name) }}">
</div>

@once
<script>
(function () {
    function bindOtp(root) {
        if (!root || root.dataset.otpBound === '1') return;
        root.dataset.otpBound = '1';

        const length = parseInt(root.dataset.otpLength || '6', 10);
        const inputs = Array.from(root.querySelectorAll('[data-otp-index]'));
        const hidden = root.querySelector('[data-otp-value]');

        function sync() {
            if (hidden) {
                hidden.value = inputs.map((el) => (el.value || '').replace(/\D/g, '').slice(0, 1)).join('');
            }
        }

        function fillFrom(start, digits) {
            const clean = String(digits || '').replace(/\D/g, '');
            for (let i = 0; i < length; i++) {
                inputs[i].value = clean[start + i] || '';
            }
            sync();
            const focusAt = Math.min(start + clean.length, length - 1);
            inputs[focusAt]?.focus();
            inputs[focusAt]?.select();
        }

        inputs.forEach((input, index) => {
            input.addEventListener('input', (event) => {
                const raw = (event.target.value || '').replace(/\D/g, '');
                if (raw.length > 1) {
                    fillFrom(index, raw);
                    return;
                }
                event.target.value = raw.slice(0, 1);
                sync();
                if (raw && index < length - 1) {
                    inputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && !input.value && index > 0) {
                    inputs[index - 1].focus();
                    inputs[index - 1].value = '';
                    sync();
                    event.preventDefault();
                }
                if (event.key === 'ArrowLeft' && index > 0) {
                    inputs[index - 1].focus();
                    event.preventDefault();
                }
                if (event.key === 'ArrowRight' && index < length - 1) {
                    inputs[index + 1].focus();
                    event.preventDefault();
                }
            });

            input.addEventListener('paste', (event) => {
                const text = event.clipboardData?.getData('text') || '';
                if (!text) return;
                event.preventDefault();
                fillFrom(0, text);
            });

            input.addEventListener('focus', () => input.select());
        });

        const form = root.closest('form');
        if (form) {
            form.addEventListener('submit', sync);
        }

        if (hidden?.value) {
            fillFrom(0, hidden.value);
        }
    }

    document.querySelectorAll('[data-otp-digits]').forEach(bindOtp);
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-otp-digits]').forEach(bindOtp);
    });
})();
</script>
@endonce
