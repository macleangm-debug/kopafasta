@props([
    'id' => null,
    'title',
    'subtitle' => null,
    'editLabel' => 'Edit',
    'cancelLabel' => 'Cancel',
])

<div x-data="{ editing: false }" class="scroll-mt-24">
    <x-admin.review-section :id="$id" :title="$title" :subtitle="$subtitle">
        <x-slot:actions>
            <button type="button"
                    @click="editing = !editing"
                    class="inline-flex text-xs font-semibold px-3 py-1.5 rounded-lg ring-1 transition-colors"
                    :class="editing ? 'text-gray-700 bg-gray-100 ring-gray-200 hover:bg-gray-200' : 'text-amber-800 bg-amber-50 ring-amber-200 hover:bg-amber-100'"
                    x-text="editing ? @js($cancelLabel) : @js($editLabel)">
            </button>
        </x-slot:actions>

        <div x-show="!editing" x-cloak>
            {{ $view }}
        </div>

        <div x-show="editing" x-cloak>
            {{ $edit }}
        </div>
    </x-admin.review-section>
</div>
