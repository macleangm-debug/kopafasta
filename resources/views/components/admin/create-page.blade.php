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

    <div class="mx-auto w-full max-w-3xl">
            <form method="POST" action="{{ $action }}" @if ($enctype) enctype="{{ $enctype }}" @endif class="glass-card rounded-3xl ring-1 ring-brand/10 p-4 sm:p-8 space-y-6" id="admin-create-form" @if ($alpine) x-data="{!! $alpine !!}" @endif>
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
                    :heading="$heading"
                    :subheading="$subheading"
                    :submitLabel="$submitLabel"
                    :cancelUrl="$cancelUrl"
                    :confirmBeforeSubmit="$confirmBeforeSubmit">
                    {{ $slot }}
                </x-admin.wizard>
            </form>
    </div>
</x-admin.layout>
