<x-admin.layout title="Users" heading="Users" subheading="All accounts — staff, borrowers, partners, and investors">
    @perm('users.view')
        @perm('users.manage')
            <x-admin.index-toolbar route="admin.users" label="New console user" />
        @endperm
        @livewire('admin.users-table')
    @else
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            You do not have permission to view users.
        </div>
    @endperm
</x-admin.layout>
