<x-admin.layout title="Profile strength" heading="Profile strength tiers" subheading="Bronze, Silver, Gold, Verified">
    @include('admin.settings.engagement._nav', ['active' => 'profile-strength'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.engagement.profile-strength.save') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            @foreach ($tiers as $i => $tier)
                <div class="grid md:grid-cols-4 gap-3 pb-4 border-b border-gray-100 last:border-0">
                    <input type="hidden" name="tiers[{{ $i }}][key]" value="{{ $tier['key'] ?? '' }}">
                    <x-admin.input name="tiers[{{ $i }}][label]" label="Label" :value="$tier['label'] ?? ''" />
                    <x-admin.input name="tiers[{{ $i }}][min_percent]" label="Min %" type="number" :value="$tier['min_percent'] ?? 0" />
                    <x-admin.input name="tiers[{{ $i }}][max_percent]" label="Max %" type="number" :value="$tier['max_percent'] ?? 100" />
                </div>
            @endforeach
        </div>
        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg">Save</button>
        </div>
    </form>
</x-admin.layout>
