@props([
    'title',
    'heading',
    'subheading' => null,
    'action',           // update URL (PUT)
    'destroyAction',    // destroy URL (DELETE)
    'cancelUrl',
    'backUrl' => null,
    'backLabel' => 'Back',
    'submitLabel' => 'Save changes',
    'deleteConfirm' => 'Delete this record? This cannot be undone.',
])

<x-admin.layout
    :title="$title"
    :heading="$heading"
    :subheading="$subheading"
    :backUrl="$backUrl ?? $cancelUrl"
    :backLabel="$backLabel">

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <form method="POST" action="{{ $action }}" class="space-y-6">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="rounded-lg bg-red-50 ring-1 ring-red-200 p-4 text-sm text-red-700">
                    <strong class="block mb-1">Please fix the following:</strong>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-admin.wizard :submitLabel="$submitLabel" :cancelUrl="$cancelUrl">
                {{ $slot }}
            </x-admin.wizard>
        </form>
    </div>

    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-red-200 p-6">
        <h3 class="text-sm font-semibold text-red-700 mb-1">Danger zone</h3>
        <p class="text-xs text-gray-500 mb-3">Deleting this record is permanent.</p>
        <form method="POST" action="{{ $destroyAction }}"
              onsubmit="return confirm('{{ $deleteConfirm }}');">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg shadow-sm transition">
                Delete
            </button>
        </form>
    </div>
</x-admin.layout>
