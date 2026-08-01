<x-admin.layout title="Add signatory" heading="Add signatory" subheading="Authorised company representative for contracts">
    @include('admin.settings._tabs', ['active' => 'signatories'])

    <form method="POST" action="{{ route('admin.settings.signatories.store') }}" enctype="multipart/form-data"
          class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-6">
        @csrf
        @include('admin.settings.signatories._form')
        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.settings.signatories.index') }}" class="text-sm text-gray-600 hover:text-gray-900 px-4 py-2">Cancel</a>
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg">Save signatory</button>
        </div>
    </form>
</x-admin.layout>
