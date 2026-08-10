<x-admin.layout title="Profile sections" heading="Profile builder" subheading="Configure profile sections without code changes">
    @include('admin.settings.engagement._nav', ['active' => 'profile-sections'])
<x-admin.index-toolbar route="admin.profile-sections" label="New section" />

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-widest text-gray-500">
                <tr>
                    <th class="px-4 py-3">Order</th>
                    <th class="px-4 py-3">Section</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Required</th>
                    <th class="px-4 py-3">Active</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($records as $record)
                    <tr>
                        <td class="px-4 py-3">{{ $record->display_order }}</td>
                        <td class="px-4 py-3 font-medium">{{ $record->name_en }} <span class="text-gray-400">({{ $record->key }})</span></td>
                        <td class="px-4 py-3">{{ $record->input_type }}</td>
                        <td class="px-4 py-3">{{ $record->is_required ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3">{{ $record->is_active ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="{{ route('admin.profile-sections.edit', $record) }}" class="text-amber-700 font-semibold hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No custom sections — borrower profile uses default section cards.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
