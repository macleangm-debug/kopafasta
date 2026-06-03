{{-- Money/number formatting is loaded globally via resources/js/money-format.js (Vite app.js). --}}
@once
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => window.initMoneyInputs?.());
    </script>
    @endpush
@endonce
