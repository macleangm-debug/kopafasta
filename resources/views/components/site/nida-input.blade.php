@props([
    'name' => 'national_id',
    'value' => '',
    'required' => true,
    'placeholder' => 'XXXXXXXX-XXXXX-XXXXX-XX',
])

<input
    {{ $attributes->merge(['class' => 'w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm font-mono']) }}
    type="text"
    inputmode="numeric"
    autocomplete="off"
    maxlength="25"
    name="{{ $name }}"
    value="{{ $value }}"
    @if ($required) required @endif
    placeholder="{{ $placeholder }}"
    x-data="{
        formatNida(raw) {
            const digits = String(raw || '').replace(/\D/g, '').slice(0, 20);
            if (digits.length <= 8) return digits;
            if (digits.length <= 13) return digits.slice(0, 8) + '-' + digits.slice(8);
            if (digits.length <= 18) return digits.slice(0, 8) + '-' + digits.slice(8, 13) + '-' + digits.slice(13);
            return digits.slice(0, 8) + '-' + digits.slice(8, 13) + '-' + digits.slice(13, 18) + '-' + digits.slice(18);
        },
        onInput(e) {
            const formatted = this.formatNida(e.target.value);
            e.target.value = formatted;
        },
        onPaste(e) {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text');
            e.target.value = this.formatNida(text);
        },
        onKeydown(e) {
            const allowed = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
            if (allowed.includes(e.key) || e.ctrlKey || e.metaKey) return;
            if (!/^\d$/.test(e.key)) e.preventDefault();
        }
    }"
    x-init="$el.value = formatNida($el.value)"
    @input="onInput($event)"
    @paste="onPaste($event)"
    @keydown="onKeydown($event)"
/>
