<x-admin.layout title="Customers" heading="Customers" subheading="Borrower registry — open a customer for the full loan officer dossier">

    <div class="mb-4 rounded-lg bg-slate-50 ring-1 ring-slate-200 px-4 py-3 text-sm text-slate-800">
        Click a customer to open their <strong>profile dossier</strong> — update KYC details, upload documents, and review applications in one place.
    </div>

    <x-admin.index-toolbar route="admin.customers" label="New customer" />
    @livewire('admin.customers-table')
</x-admin.layout>
