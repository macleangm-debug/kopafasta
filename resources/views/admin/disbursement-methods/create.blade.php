<x-admin.create-page
    title="New disbursement method" heading="New disbursement method" subheading="Payment channel for disbursements"
    :action="route('admin.disbursement-methods.store')" :cancelUrl="route('admin.disbursement-methods.index')"
    submitLabel="Create method">
    @include('admin.disbursement-methods._form', ['record' => null])
</x-admin.create-page>
