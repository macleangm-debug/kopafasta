<x-admin.layout title="Edit signatory" heading="Edit signatory" subheading="{{ $signatory->name }}">
    @include('admin.settings._tabs', ['active' => 'signatories'])

    <form method="POST" action="{{ route('admin.settings.signatories.update', $signatory) }}" enctype="multipart/form-data"
          class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-6">
        @csrf @method('PUT')
        @include('admin.settings.signatories._form', ['signatory' => $signatory])
        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.settings.signatories.index') }}" class="text-sm text-gray-600 hover:text-gray-900 px-4 py-2">Cancel</a>
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg">Update signatory</button>
        </div>
    </form>
</x-admin.layout>
