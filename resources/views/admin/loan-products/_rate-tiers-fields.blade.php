@php
    use App\Support\RatePercent;
    $record = $record ?? null;
    $existing = collect(old('rate_tiers', ($rateTiers ?? collect())->map(fn ($t) => [
        'min_amount'   => $t->min_amount ?? $t['min_amount'] ?? 0,
        'max_amount'   => $t->max_amount ?? $t['max_amount'] ?? 0,
        'monthly_rate' => RatePercent::forInput(is_object($t) ? ($t->monthly_rate ?? null) : ($t['monthly_rate'] ?? null)),
    ])->all()));
@endphp

<x-admin.step title="Tiered monthly rates">
    <div class="md:col-span-2">
        <p class="text-xs text-gray-500 mb-4">
            Set amount bands and the <strong>total monthly rate</strong> borrowers see for each band.
            Smaller loans typically use higher totals; larger loans use lower totals.
        </p>
        <div class="space-y-3" id="rate-tier-rows">
            @foreach ($existing as $i => $row)
                <div class="grid md:grid-cols-3 gap-3 rounded-lg bg-gray-50 p-3 rate-tier-row">
                    <x-admin.money-input :name="'rate_tiers['.$i.'][min_amount]'" label="Min amount (TZS)" :value="$row['min_amount']" />
                    <x-admin.money-input :name="'rate_tiers['.$i.'][max_amount]'" label="Max amount (TZS)" :value="$row['max_amount']" />
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Total monthly rate %</label>
                        <input type="text" inputmode="decimal" name="rate_tiers[{{ $i }}][monthly_rate]" value="{{ $row['monthly_rate'] }}"
                               class="tier-monthly-rate w-full text-sm bg-white border border-gray-300 rounded-lg shadow-sm px-3 py-2 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20"
                               placeholder="e.g. 12">
                    </div>
                </div>
            @endforeach
        </div>
        <button type="button" class="mt-3 text-xs font-semibold text-amber-700" onclick="addRateTierRow()">+ Add tier</button>
        <p class="mt-3 text-xs text-amber-800/90 rounded-lg bg-amber-50 ring-1 ring-amber-100 p-3" id="tier-rate-preview">
            Borrower rate range: <strong id="tier-rate-preview-value">—</strong>
        </p>
    </div>

    <div class="md:col-span-2 rounded-xl ring-1 ring-gray-200 bg-white p-5 space-y-4" id="rate-components-panel">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Monthly rate components</h3>
            <p class="text-xs text-gray-500 mt-1">
                Reference breakdown for this product (BOT + processing + risk + insurance). Tier totals above should include these components.
            </p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-admin.input name="bot_regulated_rate" label="BOT rate %" type="text" inputmode="decimal" step="0.01" max="3.5"
                           :value="RatePercent::forInput(old('bot_regulated_rate', $record?->bot_regulated_rate ?? 3.5))" placeholder="3.5" />
            <x-admin.input name="processing_fee_rate" label="Processing rate %" type="text" inputmode="decimal"
                           :value="RatePercent::forInput(old('processing_fee_rate', $record?->processing_fee_rate ?? 0))" placeholder="5" />
            <x-admin.input name="service_fee_rate" label="Risk rate %" type="text" inputmode="decimal"
                           :value="RatePercent::forInput(old('service_fee_rate', $record?->service_fee_rate ?? 0))" placeholder="3.5" />
            <x-admin.input name="administration_fee_rate" label="Insurance rate %" type="text" inputmode="decimal"
                           :value="RatePercent::forInput(old('administration_fee_rate', $record?->administration_fee_rate ?? 0))" placeholder="0" />
        </div>
        <p class="text-sm rounded-lg bg-gray-50 px-4 py-3 text-gray-800">
            Component total: <strong id="component-total-preview">—</strong>
            <span class="text-xs text-gray-500 block mt-1">Use as a guide when setting tier totals (e.g. 3.5% + 5% + 3.5% = 12%).</span>
        </p>
    </div>
</x-admin.step>

@once
    @push('scripts')
    <script>
        function parsePct(v) {
            const n = parseFloat(String(v).replace(/,/g, ''));
            if (Number.isNaN(n)) return null;
            return n > 1 ? n / 100 : n;
        }

        function fmtPct(d) {
            return (d * 100).toFixed(Math.abs(d * 100 - Math.round(d * 100)) < 0.05 ? 0 : 1) + '%';
        }

        function componentTotal() {
            let bot = parsePct(document.querySelector('[name="bot_regulated_rate"]')?.value) ?? 0;
            bot = Math.min(bot, 0.035);
            const fees = ['processing_fee_rate', 'service_fee_rate', 'administration_fee_rate']
                .reduce((sum, name) => sum + (parsePct(document.querySelector(`[name="${name}"]`)?.value) ?? 0), 0);
            return bot + fees;
        }

        function updateComponentPreview() {
            const el = document.getElementById('component-total-preview');
            if (!el) return;
            const total = componentTotal();
            el.textContent = total > 0 ? fmtPct(total) : '—';
        }

        function bindComponentInputs() {
            ['bot_regulated_rate', 'processing_fee_rate', 'service_fee_rate', 'administration_fee_rate'].forEach((name) => {
                const el = document.querySelector(`[name="${name}"]`);
                if (!el || el.dataset.componentBound === '1') return;
                el.dataset.componentBound = '1';
                el.addEventListener('input', updateComponentPreview);
            });
            updateComponentPreview();
        }

        function addRateTierRow() {
            const host = document.getElementById('rate-tier-rows');
            const i = host.querySelectorAll('.rate-tier-row').length;
            host.insertAdjacentHTML('beforeend', `
                <div class="grid md:grid-cols-3 gap-3 rounded-lg bg-gray-50 p-3 rate-tier-row">
                    <div><label class="text-xs font-semibold text-gray-700 mb-1">Min amount (TZS)</label><input type="text" inputmode="decimal" data-money-input="0" name="rate_tiers[${i}][min_amount]" class="mt-0 w-full rounded-lg border-gray-300 text-sm px-3 py-2"></div>
                    <div><label class="text-xs font-semibold text-gray-700 mb-1">Max amount (TZS)</label><input type="text" inputmode="decimal" data-money-input="0" name="rate_tiers[${i}][max_amount]" class="mt-0 w-full rounded-lg border-gray-300 text-sm px-3 py-2"></div>
                    <div><label class="text-xs font-semibold text-gray-700 mb-1">Total monthly rate %</label><input type="text" inputmode="decimal" name="rate_tiers[${i}][monthly_rate]" placeholder="e.g. 12" class="tier-monthly-rate mt-0 w-full rounded-lg border-gray-300 text-sm px-3 py-2"></div>
                </div>`);
            window.initMoneyInputs?.();
            bindTierPreview();
        }

        function bindTierPreview() {
            document.querySelectorAll('.tier-monthly-rate, [name^="rate_tiers"][name$="[monthly_rate]"]').forEach((el) => {
                el.removeEventListener('input', updateTierPreview);
                el.addEventListener('input', updateTierPreview);
            });
            updateTierPreview();
        }

        function updateTierPreview() {
            const rates = [...document.querySelectorAll('[name^="rate_tiers"][name$="[monthly_rate]"]')]
                .map((el) => parsePct(el.value))
                .filter((r) => r !== null && r > 0);
            const out = document.getElementById('tier-rate-preview-value');
            if (!out) return;
            if (!rates.length) {
                out.textContent = '—';
                return;
            }
            const min = Math.min(...rates);
            const max = Math.max(...rates);
            out.textContent = Math.abs(min - max) < 0.0001 ? fmtPct(min) : fmtPct(min) + ' – ' + fmtPct(max);
        }

        document.addEventListener('DOMContentLoaded', () => {
            bindTierPreview();
            bindComponentInputs();
        });
    </script>
    @endpush
@endonce
