@props([
    'title',
    'heading',
    'subheading' => null,
    'action',           // form action URL (store)
    'cancelUrl',
    'backLabel' => 'Back',
    'submitLabel' => 'Create',
    'enctype' => null,
    'confirmBeforeSubmit' => false,
    'alpine' => null,
])

<x-admin.layout
    :title="$title"
    heading=""
    :backUrl="$cancelUrl"
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
            <form method="POST" action="{{ $action }}" @if ($enctype) enctype="{{ $enctype }}" @endif class="space-y-6" id="admin-create-form" @if ($alpine) x-data="{!! $alpine !!}" @endif>
                @csrf

                @if ($errors->any())
                    <div data-server-errors class="rounded-xl bg-red-50 ring-1 ring-red-200 p-4 text-sm text-red-700">
                        <strong class="block mb-1">Please fix the following:</strong>
                        <ul class="list-disc ml-5 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <x-admin.wizard
                    :submitLabel="$submitLabel"
                    :cancelUrl="$cancelUrl"
                    :confirmBeforeSubmit="$confirmBeforeSubmit">
                    {{ $slot }}
                </x-admin.wizard>
            </form>
        </div>
    </div>
</x-admin.layout>
