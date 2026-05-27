<x-admin.layout title="Departments" heading="Departments" subheading="Org units within branches">
    <x-admin.index-toolbar route="admin.departments" label="New department" />
    @livewire('admin.departments-table')
</x-admin.layout>
