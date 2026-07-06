<x-admin.layout title="Community milestones" heading="Community milestones" subheading="Rewards for helping others join">
    @include('admin.settings.engagement._nav', ['active' => 'milestones'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.engagement.milestones.save') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            @foreach ($milestones as $i => $milestone)
                <div class="grid md:grid-cols-2 gap-3 pb-4 border-b border-gray-100 last:border-0">
                    <input type="hidden" name="milestones[{{ $i }}][key]" value="{{ $milestone['key'] ?? 'm'.$i }}">
                    <x-admin.input name="milestones[{{ $i }}][title]" label="Title" :value="$milestone['title'] ?? ''" />
                    <x-admin.input name="milestones[{{ $i }}][target]" label="Target" type="number" :value="$milestone['target'] ?? ''" />
                    <div class="md:col-span-2">
                        <x-admin.textarea name="milestones[{{ $i }}][rewards]" label="Rewards (one per line)" rows="3"
                            :value="implode(\"\\n\", $milestone['rewards'] ?? [])" />
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg">Save</button>
        </div>
    </form>
</x-admin.layout>
