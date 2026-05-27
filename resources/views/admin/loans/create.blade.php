<x-admin.layout title="New loan" heading="New loan" subheading="Create a loan record">

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <form method="POST" action="{{ route('admin.loans.store') }}" class="space-y-6">
            @csrf
            @include('admin.loans._form', ['loan' => null])

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('admin.loans.index') }}"
                   class="text-sm font-medium text-gray-600 hover:text-gray-800 px-4 py-2">Cancel</a>
                <button type="submit"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2 rounded-lg shadow-sm transition">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Create loan
                </button>
            </div>
        </form>
    </div>
</x-admin.layout>
