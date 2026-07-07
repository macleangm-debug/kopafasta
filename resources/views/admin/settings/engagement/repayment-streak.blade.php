<x-admin.layout title="Repayment streak" heading="Repayment streak" subheading="Reward consecutive on-time repayments with loyalty points">
    @include('admin.settings.engagement._nav', ['active' => 'repayment-streak'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    @php
        $defaults = config('gamification.repayment_streak.milestones', []);
        $rows = collect($values['milestones'] ?? $defaults)->map(function ($milestone, $index) use ($defaults) {
            if (is_array($milestone)) {
                return [
                    'count' => (int) ($milestone['count'] ?? 0),
                    'points' => (int) ($milestone['points'] ?? $milestone['percent'] ?? 0),
                ];
            }

            $fallback = $defaults[$index] ?? ['points' => 10];

            return [
                'count' => (int) $milestone,
                'points' => (int) ($fallback['points'] ?? 10),
            ];
        })->filter(fn ($row) => $row['count'] > 0)->values();
        if ($rows->isEmpty()) {
            $rows = collect($defaults);
        }
    @endphp

    <form method="POST" action="{{ route('admin.settings.engagement.repayment-streak.save') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 grid md:grid-cols-2 gap-4">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked($values['enabled'] ?? true)> Enabled</label>
            <x-admin.input name="reward_label" label="Reward label" :value="$values['reward_label'] ?? 'Repayment streak points'" />
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-900">Milestones</h3>
            <p class="text-xs text-gray-500">Each row awards points when the borrower reaches that many consecutive on-time repayments.</p>
            <div class="space-y-3">
                @foreach ($rows as $index => $row)
                    <div class="grid sm:grid-cols-2 gap-3">
                        <x-admin.input :name="'milestone_rows['.$index.'][count]'" type="number" label="Repayment count" :value="$row['count']" />
                        <x-admin.input :name="'milestone_rows['.$index.'][points]'" type="number" label="Points" :value="$row['points']" />
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg">Save</button>
        </div>
    </form>
</x-admin.layout>
