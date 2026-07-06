<x-admin.layout title="Trust score" heading="Trust score" subheading="Star rating based on member behaviour">
    @include('admin.settings.engagement._nav', ['active' => 'trust-score'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

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
