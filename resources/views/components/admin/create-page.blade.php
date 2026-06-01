@props([
    'title',
    'heading',
    'subheading' => null,
    'action',           // form action URL (store)
    'cancelUrl',
    'backLabel' => 'Back',
    'submitLabel' => 'Create',
])

<x-admin.layout
    :title="$title"
    :heading="$heading"
    :subheading="$subheading"
    :backUrl="$cancelUrl"
    :backLabel="$backLabel">

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <form method="POST" action="{{ $action }}" class="space-y-6">
            @csrf

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
</x-admin.layout>
