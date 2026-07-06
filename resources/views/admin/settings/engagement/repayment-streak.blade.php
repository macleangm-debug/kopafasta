<x-admin.layout title="Repayment streak" heading="Repayment streak" subheading="Reward consecutive on-time repayments">
    @include('admin.settings.engagement._nav', ['active' => 'repayment-streak'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.engagement.repayment-streak.save') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 grid md:grid-cols-2 gap-4">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked($values['enabled'] ?? true)> Enabled</label>
            <x-admin.input name="reward_label" label="Reward label" :value="$values['reward_label'] ?? 'Interest discount'" />
            <div class="md:col-span-2">
                <x-admin.input name="milestones" label="Milestone counts (comma-separated)" :value="implode(', ', $values['milestones'] ?? [3,5,8,12])" />
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg">Save</button>
        </div>
    </form>
</x-admin.layout>
