<x-admin.layout title="Users" heading="System Users" subheading="Console accounts and roles">
    <x-admin.index-toolbar route="admin.users" label="New user" />
    @livewire('admin.users-table')
</x-admin.layout>
