<x-admin.layout title="Underwriting boosts" heading="Underwriting boosts" subheading="Referral level and trust score multipliers for limits, rates, and processing priority">
    @include('admin.settings.engagement._nav', ['active' => 'underwriting'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    @php
        $referralLevels = $values['referral_level'] ?? config('gamification.underwriting_boosts.referral_level', []);
        $trust = $values['trust_score'] ?? config('gamification.underwriting_boosts.trust_score', []);
    @endphp

    <form method="POST" action="{{ route('admin.settings.engagement.underwriting.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Referral level boosts</h3>
            <p class="text-xs text-gray-500">Applied when calculating loan limits, quoted rates, and review queue priority.</p>
            @foreach ($levels as $level)
                @php $key = $level['key'] ?? ''; $boost = $referralLevels[$key] ?? []; @endphp
                <div class="grid md:grid-cols-4 gap-3 pb-4 border-b border-gray-100 last:border-0">
                    <div class="md:col-span-4 text-sm font-medium text-gray-800">{{ $level['label'] ?? ucfirst($key) }}</div>
                    <x-admin.input name="referral_level[{{ $key }}][limit_multiplier]" label="Limit multiplier" type="number" step="0.01" min="1" :value="$boost['limit_multiplier'] ?? 1" />
                    <x-admin.input name="referral_level[{{ $key }}][rate_discount]" label="Rate discount (fraction)" type="number" step="0.001" min="0" :value="$boost['rate_discount'] ?? 0" />
                    <x-admin.input name="referral_level[{{ $key }}][processing_priority]" label="Processing priority" type="number" min="0" :value="$boost['processing_priority'] ?? 0" />
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 grid md:grid-cols-3 gap-4">
            <h3 class="md:col-span-3 text-sm font-semibold text-gray-700">Trust score per step (20% bands, max 5 steps)</h3>
            <x-admin.input name="trust_score[limit_multiplier_per_step]" label="Limit multiplier per step" type="number" step="0.01" :value="$trust['limit_multiplier_per_step'] ?? 0.02" />
            <x-admin.input name="trust_score[rate_discount_per_step]" label="Rate discount per step" type="number" step="0.001" :value="$trust['rate_discount_per_step'] ?? 0.002" />
            <x-admin.input name="trust_score[processing_priority_per_step]" label="Priority per step" type="number" step="0.1" :value="$trust['processing_priority_per_step'] ?? 0.5" />
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg">Save</button>
        </div>
    </form>
</x-admin.layout>
