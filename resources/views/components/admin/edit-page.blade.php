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
    'deleteTitle' => 'Delete this record?',
    'deleteLabel' => 'Delete',
    'deleteHint' => 'Deleting this record is permanent.',
    'enctype' => null,
])

<x-admin.layout
    :title="$title"
    heading=""
    :backUrl="$backUrl ?? $cancelUrl"
    :backLabel="$backLabel">

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-brand/10">
        <div class="bg-gradient-to-r from-brand via-brand to-brand-light px-6 py-5 text-white">
            <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ brand_name() }}</p>
            <h1 class="text-xl font-bold tracking-tight mt-1">{{ $heading }}</h1>
            @if ($subheading)
                <p class="text-sm text-white/75 mt-1">{{ $subheading }}</p>
            @endif
        </div>
        <div class="p-6">
            <form method="POST" action="{{ $action }}" @if ($enctype) enctype="{{ $enctype }}" @endif class="space-y-6">
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <div class="rounded-xl bg-red-50 ring-1 ring-red-200 p-4 text-sm text-red-700">
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
    </div>

    <div class="mt-6 bg-white rounded-2xl shadow-sm ring-1 ring-red-200/80 p-6">
        <h3 class="text-sm font-semibold text-red-700 mb-1">Danger zone</h3>
        <p class="text-xs text-gray-500 mb-3">{{ $deleteHint }}</p>
        <form method="POST" action="{{ $destroyAction }}"
              x-data
              @submit.prevent="window.confirmForm($el, {
                  title: @js($deleteTitle),
                  message: @js($deleteConfirm),
                  confirmLabel: @js($deleteLabel),
                  confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                  tone: 'warning',
              })">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-xl shadow-sm transition">
                {{ $deleteLabel }}
            </button>
        </form>
    </div>
</x-admin.layout>
