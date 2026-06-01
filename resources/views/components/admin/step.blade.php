{{--
    Wizard step container. Use multiple <x-admin.step title="..."> inside a form
    that is rendered by <x-admin.create-page> / <x-admin.edit-page>. The page
    component auto-detects [data-step] children and turns them into a wizard.

    Usage:
        <x-admin.step title="Basic info">
            <x-admin.input name="name" label="Name" required />
            ...
        </x-admin.step>
--}}
@props(['title', 'id' => null])

<div @if($id) id="{{ $id }}" @endif data-step data-step-label="{{ $title }}"
     class="grid grid-cols-1 md:grid-cols-2 gap-5">
    {{ $slot }}
</div>
