@php
    $existing = collect(old('rate_tiers', ($rateTiers ?? collect())->map(fn ($t) => [
        'min_amount'   => $t->min_amount ?? 0,
        'max_amount'   => $t->max_amount ?? 0,
        'monthly_rate' => $t->monthly_rate ?? 0,
    ])->all()));
@endphp

<x-admin.step title="Tiered monthly rates">
    <div class="md:col-span-2">
        <p class="text-xs text-gray-500 mb-4">Higher loan amounts can use lower monthly rates. Leave empty to use the product default interest rate only.</p>
        <div class="space-y-3" id="rate-tier-rows">
            @foreach ($existing as $i => $row)
                <div class="grid md:grid-cols-3 gap-3 rounded-lg bg-gray-50 p-3">
                    <x-admin.input :name="'rate_tiers['.$i.'][min_amount]'" label="Min amount (TZS)" type="number" step="0.01" :value="$row['min_amount']" />
                    <x-admin.input :name="'rate_tiers['.$i.'][max_amount]'" label="Max amount (TZS)" type="number" step="0.01" :value="$row['max_amount']" />
                    <x-admin.input :name="'rate_tiers['.$i.'][monthly_rate]'" label="Monthly rate (decimal)" type="number" step="0.0001" :value="$row['monthly_rate']" />
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
                    <div><label class="text-xs font-medium text-gray-600">Monthly rate (decimal)</label><input type="number" step="0.0001" name="rate_tiers[${i}][monthly_rate]" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></div>
                </div>`);
        }
    </script>
    @endpush
@endonce
