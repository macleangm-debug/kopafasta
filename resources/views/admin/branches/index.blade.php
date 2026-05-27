<x-admin.layout title="Branches" heading="Branches" subheading="Physical and virtual branches">
    <x-admin.index-toolbar route="admin.branches" label="New branch" />
    @livewire('admin.branches-table')
</x-admin.layout>
