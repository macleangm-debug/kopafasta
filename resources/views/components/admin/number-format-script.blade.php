@once
    @push('scripts')
    <script>
        window.initMoneyInputs = function initMoneyInputs(root = document) {
            root.querySelectorAll('[data-money-input]').forEach((input) => {
                if (input.dataset.moneyBound === '1') return;
                input.dataset.moneyBound = '1';
                const decimals = parseInt(input.dataset.moneyInput || '0', 10);

                const format = (raw) => {
                    const cleaned = String(raw).replace(/[^\d.]/g, '');
                    if (cleaned === '') return '';
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
            });
        };

        document.addEventListener('DOMContentLoaded', () => initMoneyInputs());
    </script>
    @endpush
@endonce
