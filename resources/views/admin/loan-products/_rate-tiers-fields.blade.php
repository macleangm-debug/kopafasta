@php
    use App\Support\RatePercent;
    $existing = collect(old('rate_tiers', ($rateTiers ?? collect())->map(fn ($t) => [
        'min_amount'   => $t->min_amount ?? $t['min_amount'] ?? 0,
        'max_amount'   => $t->max_amount ?? $t['max_amount'] ?? 0,
        'monthly_rate' => RatePercent::forInput(is_object($t) ? ($t->monthly_rate ?? null) : ($t['monthly_rate'] ?? null)),
    ])->all()));
@endphp

<x-admin.step title="Tiered monthly rates">
    <div class="md:col-span-2">
        <p class="text-xs text-gray-500 mb-4">
            Enter the <strong>total monthly rate</strong> borrowers see for each loan amount band — including BOT-regulated interest, processing fee, risk fee, and administration (e.g. 3.5% + 5% + 3.5% = 12%).
            Smaller loans use higher rates; larger loans use lower rates.
        </p>
        <div class="space-y-3" id="rate-tier-rows">
            @foreach ($existing as $i => $row)
                <div class="grid md:grid-cols-3 gap-3 rounded-lg bg-gray-50 p-3 rate-tier-row">
                    <x-admin.money-input :name="'rate_tiers['.$i.'][min_amount]'" label="Min amount (TZS)" :value="$row['min_amount']" />
                    <x-admin.money-input :name="'rate_tiers['.$i.'][max_amount]'" label="Max amount (TZS)" :value="$row['max_amount']" />
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Total monthly rate %</label>
                        <input type="text" inputmode="decimal" name="rate_tiers[{{ $i }}][monthly_rate]" value="{{ $row['monthly_rate'] }}"
                               class="w-full text-sm bg-white border border-gray-300 rounded-lg shadow-sm px-3 py-2 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20"
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
</x-admin.step>

@once
    @push('scripts')
    <script>
        function addRateTierRow() {
            const host = document.getElementById('rate-tier-rows');
            const i = host.querySelectorAll('.rate-tier-row').length;
            host.insertAdjacentHTML('beforeend', `
                <div class="grid md:grid-cols-3 gap-3 rounded-lg bg-gray-50 p-3 rate-tier-row">
                    <div><label class="text-xs font-semibold text-gray-700 mb-1">Min amount (TZS)</label><input type="text" inputmode="decimal" data-money-input="0" name="rate_tiers[${i}][min_amount]" class="mt-0 w-full rounded-lg border-gray-300 text-sm px-3 py-2"></div>
                    <div><label class="text-xs font-semibold text-gray-700 mb-1">Max amount (TZS)</label><input type="text" inputmode="decimal" data-money-input="0" name="rate_tiers[${i}][max_amount]" class="mt-0 w-full rounded-lg border-gray-300 text-sm px-3 py-2"></div>
                    <div><label class="text-xs font-semibold text-gray-700 mb-1">Total monthly rate %</label><input type="text" inputmode="decimal" name="rate_tiers[${i}][monthly_rate]" placeholder="e.g. 12" class="mt-0 w-full rounded-lg border-gray-300 text-sm px-3 py-2"></div>
                </div>`);
            window.initMoneyInputs?.();
            bindTierPreview();
        }

        function parsePct(v) {
            const n = parseFloat(String(v).replace(/,/g, ''));
            if (Number.isNaN(n)) return null;
            return n > 1 ? n / 100 : n;
        }

        function bindTierPreview() {
            document.querySelectorAll('[name^="rate_tiers"][name$="[monthly_rate]"]').forEach((el) => {
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
            const fmt = (d) => (d * 100).toFixed(d === Math.floor(d * 100) / 100 ? 0 : 1) + '%';
            out.textContent = Math.abs(min - max) < 0.0001 ? fmt(min) : fmt(min) + ' – ' + fmt(max);
        }

        document.addEventListener('DOMContentLoaded', () => {
            bindTierPreview();
        });
    </script>
    @endpush
@endonce
