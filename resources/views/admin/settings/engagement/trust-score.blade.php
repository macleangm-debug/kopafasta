<x-admin.layout title="Trust score" heading="Trust score" subheading="Star rating based on member behaviour">
    @include('admin.settings.engagement._nav', ['active' => 'trust-score'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    @include('admin.settings.engagement._guide', [
        'title' => 'How trust score works',
        'summary' => 'Trust score is a 0–100% composite of member behaviour. Weights below should add up to about 100. The percent is shown as stars (max stars) and is split into 20% bands for underwriting boost steps. Raising profile_completion weight makes finishing the profile hub more valuable for loan terms.',
        'borrowerSees' => [
            'Dashboard financial-health panel: trust percent / stars.',
            'Engagement hub: trust progress and unlock benefit bullets (from the list below).',
            'Loan quote: extra limit/rate/priority from trust steps (configured on Underwriting boosts).',
        ],
        'fields' => [
            'Max stars' => 'Display only (e.g. 5). Percent still drives underwriting steps.',
            'Weights' => 'Relative importance of on-time payments, profile completion, referrals, account age, and successful loans.',
            'Unlock benefits' => 'Marketing lines shown to the member — not enforced automatically. Real loan effects come from Underwriting boosts.',
        ],
        'example' => 'Weights: payments 30, profile 25, referrals 15, age 10, loans 20. A member with strong profile + on-time history might land at ~70% → 3 trust steps → +0.06 limit multiplier if per-step is 0.02.',
        'tips' => [
            'If you want profile completion to move the needle on limits, keep profile_completion weight high and trust per-step multipliers non-zero on Underwriting boosts.',
            'Benefits text should match what Underwriting boosts actually deliver so ops and members stay aligned.',
        ],
    ])

    <form method="POST" action="{{ route('admin.settings.engagement.trust-score.save') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 grid md:grid-cols-2 gap-4">
            <x-admin.input name="max_stars" label="Max stars" type="number" :value="$values['max_stars'] ?? 5" />
            @foreach (($values['weights'] ?? config('gamification.trust_score.weights')) as $key => $weight)
                <x-admin.input name="weights[{{ $key }}]" :label="str_replace('_', ' ', ucfirst($key)).' weight'" type="number" :value="$weight" />
            @endforeach
            <div class="md:col-span-2">
                <x-admin.textarea name="benefits" label="Unlock benefits (one per line)" rows="4"
                    :value="implode(\"\\n\", $values['benefits'] ?? config('gamification.trust_score.benefits', []))" />
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg">Save</button>
        </div>
    </form>
</x-admin.layout>
