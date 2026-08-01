@php
    use App\Support\RatePercent;
    $existing = collect(old('rate_tiers', ($rateTiers ?? collect())->map(function ($t) {
        $isModel = is_object($t);

        return [
            'min_amount'              => $isModel ? $t->min_amount : ($t['min_amount'] ?? 0),
            'max_amount'              => $isModel ? $t->max_amount : ($t['max_amount'] ?? 0),
            'bot_regulated_rate'      => RatePercent::forInput($isModel ? $t->bot_regulated_rate : ($t['bot_regulated_rate'] ?? null)),
            'processing_fee_rate'     => RatePercent::forInput($isModel ? $t->processing_fee_rate : ($t['processing_fee_rate'] ?? null)),
            'service_fee_rate'        => RatePercent::forInput($isModel ? $t->service_fee_rate : ($t['service_fee_rate'] ?? null)),
            'administration_fee_rate' => RatePercent::forInput($isModel ? $t->administration_fee_rate : ($t['administration_fee_rate'] ?? null)),
            'monthly_rate'            => $isModel ? (float) $t->monthly_rate : (float) ($t['monthly_rate'] ?? 0),
        ];
    })->all()));
@endphp

<x-admin.step title="Tiered monthly rates">
    <div class="md:col-span-2">
        <p class="text-xs text-gray-500 mb-4">
            Configure amount bands for this product. Each tier shows the <strong>total monthly rate</strong> on the summary;
            expand a tier to enter BOT, processing, risk, and insurance — the total updates automatically.
        </p>
        <div class="space-y-3" id="rate-tier-rows">
            @foreach ($existing as $i => $row)
                @include('admin.loan-products._rate-tier-row', ['index' => $i, 'row' => $row, 'open' => true])
            @endforeach
        </div>
        <template id="rate-tier-row-template">
            @include('admin.loan-products._rate-tier-row', ['index' => '__INDEX__', 'row' => [
                'min_amount' => '',
                'max_amount' => '',
                'bot_regulated_rate' => '3.5',
                'processing_fee_rate' => '5',
                'service_fee_rate' => '3.5',
                'administration_fee_rate' => '0',
                'monthly_rate' => 0.12,
            ], 'open' => true])
        </template>
        <button type="button" class="mt-3 text-xs font-semibold text-amber-700" onclick="addRateTierRow()">+ Add tier</button>
        @if (! empty($record))
            <button type="button" class="mt-3 ml-3 text-xs font-semibold text-gray-700 hover:text-brand-light underline"
                    form="regenerate-rate-tiers-{{ $record->id }}"
                    onclick="return confirm('Replace all tiers with the default amount-band template for this product?');">
                Regenerate default tiers
            </button>
        @endif
        <p class="mt-3 text-xs text-amber-800/90 rounded-lg bg-amber-50 ring-1 ring-amber-100 p-3" id="tier-rate-preview">
            Borrower rate range: <strong id="tier-rate-preview-value">—</strong>
        </p>
    </div>
</x-admin.step>

@if (! empty($record))
    <form id="regenerate-rate-tiers-{{ $record->id }}" method="POST" action="{{ route('admin.loan-products.regenerate-rate-tiers', $record) }}" class="hidden">
        @csrf
    </form>
@endif

@once
    @push('scripts')
    <script>
        function parsePct(v) {
            const n = parseFloat(String(v ?? '').replace(/,/g, ''));
            if (Number.isNaN(n)) return 0;
            return n > 1 ? n / 100 : n;
        }

        function fmtPct(d) {
            if (!d || d <= 0) return '—';
            return (d * 100).toFixed(Math.abs(d * 100 - Math.round(d * 100)) < 0.05 ? 0 : 1) + '%';
        }

        function tierRowTotal(row) {
            let bot = parsePct(row.querySelector('[data-tier-bot]')?.value);
            bot = Math.min(bot, 0.035);
            const processing = parsePct(row.querySelector('[data-tier-processing]')?.value);
            const risk = parsePct(row.querySelector('[data-tier-risk]')?.value);
            const insurance = parsePct(row.querySelector('[data-tier-insurance]')?.value);
            return Math.round((bot + processing + risk + insurance) * 10000) / 10000;
        }

        function updateTierRowSummary(row) {
            const total = tierRowTotal(row);
            row.querySelectorAll('[data-tier-summary-total], [data-tier-inline-total]').forEach((el) => {
                el.textContent = fmtPct(total);
            });
            const hidden = row.querySelector('[data-tier-monthly-hidden]');
            if (hidden) hidden.value = total > 0 ? total : '';

            const min = row.querySelector('[name$="[min_amount]"]')?.value ?? '';
            const max = row.querySelector('[name$="[max_amount]"]')?.value ?? '';
            const band = row.querySelector('[data-tier-summary-band]');
            if (band && (min || max)) {
                band.textContent = 'TZS ' + min + ' – ' + max;
            }
            return total;
        }

        function bindTierRow(row) {
            if (!row || row.dataset.tierBound === '1') return;
            row.dataset.tierBound = '1';
            row.querySelectorAll('[data-tier-component]').forEach((input) => {
                input.addEventListener('input', () => {
                    updateTierRowSummary(row);
                    updateTierPreview();
                });
            });
            row.querySelectorAll('[data-tier-band]').forEach((input) => {
                input.addEventListener('input', () => updateTierRowSummary(row));
            });
            updateTierRowSummary(row);
        }

        function bindAllTierRows() {
            document.querySelectorAll('.rate-tier-row').forEach(bindTierRow);
            updateTierPreview();
        }

        function updateTierPreview() {
            const totals = [...document.querySelectorAll('.rate-tier-row')]
                .map((row) => tierRowTotal(row))
                .filter((r) => r > 0);
            const out = document.getElementById('tier-rate-preview-value');
            if (!out) return;
            if (!totals.length) {
                out.textContent = '—';
                return;
            }
            const min = Math.min(...totals);
            const max = Math.max(...totals);
            out.textContent = Math.abs(min - max) < 0.0001 ? fmtPct(min) : fmtPct(min) + ' – ' + fmtPct(max);
        }

        function addRateTierRow() {
            const tpl = document.getElementById('rate-tier-row-template');
            const host = document.getElementById('rate-tier-rows');
            if (!tpl || !host) return;
            const i = host.querySelectorAll('.rate-tier-row').length;
            const html = tpl.innerHTML.replace(/__INDEX__/g, String(i));
            const wrap = document.createElement('div');
            wrap.innerHTML = html;
            const row = wrap.firstElementChild;
            host.appendChild(row);
            window.initMoneyInputs?.(row);
            bindTierRow(row);
            updateTierPreview();
        }

        document.addEventListener('DOMContentLoaded', () => {
            bindAllTierRows();
        });
    </script>
    @endpush
@endonce
