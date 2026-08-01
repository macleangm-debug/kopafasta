<x-admin.layout title="Edit ward" heading="Edit ward" :subheading="$ward->name">
    @include('admin.settings._tabs', ['active' => 'locations'])

    <form method="POST" action="{{ route('admin.settings.locations.update', $ward) }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4 max-w-2xl">
        @csrf @method('PUT')
        @include('admin.settings.locations._form', ['ward' => $ward])
        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg">Save changes</button>
            <a href="{{ route('admin.settings.locations.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900 py-2">Cancel</a>
        </div>
    </form>
</x-admin.layout>
