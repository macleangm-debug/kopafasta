<x-admin.layout title="Branches" heading="Branches" subheading="Digital-first: Head Office serves all online borrowers">
    <div class="mb-4 rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-3 text-sm text-sky-900">
        KopaFasta operates as a digital-first lender. Physical branches are optional for staff routing; borrowers are not asked to select a branch.
    </div>
    <x-admin.index-toolbar route="admin.branches" label="New branch" />
    @livewire('admin.branches-table')
</x-admin.layout>
