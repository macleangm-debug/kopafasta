<x-admin.create-page
    title="New bank account" heading="New bank account" subheading="Add a corporate bank account"
    :action="route('admin.bank-accounts.store')" :cancelUrl="route('admin.bank-accounts.index')"
    submitLabel="Create bank account">
    @include('admin.bank-accounts._form', ['record' => null])
</x-admin.create-page>
