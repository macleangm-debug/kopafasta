<x-admin.layout :title="$record->name_en" :heading="$record->name_en" subheading="Profile section definition">
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-2 text-sm">
        <p><span class="text-gray-500">Key:</span> <span class="font-mono">{{ $record->key }}</span></p>
        <p><span class="text-gray-500">Input type:</span> {{ $record->input_type }}</p>
        <p><span class="text-gray-500">Display order:</span> {{ $record->display_order }}</p>
        <p><span class="text-gray-500">Required:</span> {{ $record->is_required ? 'Yes' : 'No' }}</p>
        <div class="pt-4 flex gap-3">
            <a href="{{ route('admin.profile-sections.edit', $record) }}" class="text-amber-700 font-semibold hover:underline">Edit</a>
            <a href="{{ route('admin.profile-sections.index') }}" class="text-gray-600 hover:underline">Back</a>
        </div>
    </div>
</x-admin.layout>
