<x-admin.layout title="Add ward" heading="Add ward" subheading="Create a ward under an existing district">
    @include('admin.settings._tabs', ['active' => 'locations'])

    <form method="POST" action="{{ route('admin.settings.locations.store') }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4 max-w-2xl">
        @csrf
        @include('admin.settings.locations._form')
        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg">Save ward</button>
            <a href="{{ route('admin.settings.locations.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900 py-2">Cancel</a>
        </div>
    </form>
</x-admin.layout>
