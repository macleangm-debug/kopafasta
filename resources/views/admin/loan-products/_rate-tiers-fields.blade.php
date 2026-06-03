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
            Smaller loans use higher monthly rates; larger loans use lower rates. Enter each rate as a percentage (e.g. 17 for 17%).
            These totals are what borrowers see when tiers apply. Default rows are pre-filled for new products.
        </p>
        <div class="space-y-3" id="rate-tier-rows">
            @foreach ($existing as $i => $row)
                <div class="grid md:grid-cols-3 gap-3 rounded-lg bg-gray-50 p-3">
                    <x-admin.input :name="'rate_tiers['.$i.'][min_amount]'" label="Min amount (TZS)" type="number" step="0.01" :value="$row['min_amount']" />
                    <x-admin.input :name="'rate_tiers['.$i.'][max_amount]'" label="Max amount (TZS)" type="number" step="0.01" :value="$row['max_amount']" />
                    <x-admin.input :name="'rate_tiers['.$i.'][monthly_rate]'" label="Monthly rate %" type="number" step="0.1" :value="$row['monthly_rate']" />
                </div>
            @endforeach
        </div>
        <button type="button" class="mt-3 text-xs font-semibold text-amber-700" onclick="addRateTierRow()">+ Add tier</button>
    </div>
</x-admin.step>

@once
    @push('scripts')
    <script>
        let rateTierIndex = {{ $existing->count() }};
        function addRateTierRow() {
            const host = document.getElementById('rate-tier-rows');
            const i = rateTierIndex++;
            host.insertAdjacentHTML('beforeend', `
                <div class="grid md:grid-cols-3 gap-3 rounded-lg bg-gray-50 p-3">
                    <div><label class="text-xs font-medium text-gray-600">Min amount (TZS)</label><input type="number" step="0.01" name="rate_tiers[${i}][min_amount]" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></div>
                    <div><label class="text-xs font-medium text-gray-600">Max amount (TZS)</label><input type="number" step="0.01" name="rate_tiers[${i}][max_amount]" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></div>
                    <div><label class="text-xs font-medium text-gray-600">Monthly rate %</label><input type="number" step="0.1" name="rate_tiers[${i}][monthly_rate]" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></div>
                </div>`);
        }
    </script>
    @endpush
@endonce
