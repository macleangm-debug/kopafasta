<x-admin.layout title="Referral levels" heading="Referral levels" subheading="Bronze, Silver, Gold, Diamond tiers">
    @include('admin.settings.engagement._nav', ['active' => 'referral-levels'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.engagement.referral-levels.save') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Levels</h3>
            @foreach ($levels as $i => $level)
                <div class="grid md:grid-cols-4 gap-3 pb-4 border-b border-gray-100 last:border-0">
                    <input type="hidden" name="levels[{{ $i }}][key]" value="{{ $level['key'] ?? '' }}">
                    <x-admin.input name="levels[{{ $i }}][label]" label="Label" :value="$level['label'] ?? ''" />
                    <x-admin.input name="levels[{{ $i }}][min_referrals]" label="Min referrals" type="number" :value="$level['min_referrals'] ?? 0" />
                    <x-admin.input name="levels[{{ $i }}][max_referrals]" label="Max referrals (blank = unlimited)" type="number" :value="$level['max_referrals'] ?? ''" />
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Progress milestones</h3>
            @foreach (($milestones ?? []) as $i => $milestone)
                <div class="grid md:grid-cols-2 gap-3">
                    <x-admin.input name="milestones[{{ $i }}][target]" label="Target referrals" type="number" :value="$milestone['target'] ?? ''" />
                    <x-admin.input name="milestones[{{ $i }}][reward_label]" label="Next reward label" :value="$milestone['reward_label'] ?? ''" />
                </div>
            @endforeach
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg">Save</button>
        </div>
    </form>
</x-admin.layout>
