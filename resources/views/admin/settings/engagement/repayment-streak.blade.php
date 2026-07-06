<x-admin.layout title="Repayment streak" heading="Repayment streak" subheading="Reward consecutive on-time repayments with application fee discounts">
    @include('admin.settings.engagement._nav', ['active' => 'repayment-streak'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    @php
        $defaults = config('gamification.repayment_streak.milestones', []);
        $rows = collect($values['milestones'] ?? $defaults)->map(function ($milestone, $index) use ($defaults) {
            if (is_array($milestone)) {
                return [
                    'count' => (int) ($milestone['count'] ?? 0),
                    'percent' => (float) ($milestone['percent'] ?? 0),
                ];
            }

            $fallback = $defaults[$index] ?? ['percent' => 10];

            return [
                'count' => (int) $milestone,
                'percent' => (float) ($fallback['percent'] ?? 10),
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
            <x-admin.input name="reward_label" label="Reward label" :value="$values['reward_label'] ?? 'Application fee discount'" />
            <x-admin.input name="fee_type" label="Fee type" :value="$values['fee_type'] ?? 'application_fee'" />
            <x-admin.input name="max_discount_percent" type="number" step="0.1" label="Max discount (%)" :value="$values['max_discount_percent'] ?? 30" />
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-900">Milestones</h3>
            <p class="text-xs text-gray-500">Each row unlocks a discount when the borrower reaches that many consecutive on-time repayments.</p>
            <div class="space-y-3">
                @foreach ($rows as $index => $row)
                    <div class="grid sm:grid-cols-2 gap-3">
                        <x-admin.input :name="'milestone_rows['.$index.'][count]'" type="number" label="Repayment count" :value="$row['count']" />
                        <x-admin.input :name="'milestone_rows['.$index.'][percent]'" type="number" step="0.1" label="Discount (%)" :value="$row['percent']" />
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg">Save</button>
        </div>
    </form>
</x-admin.layout>
