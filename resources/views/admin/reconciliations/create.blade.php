<x-admin.create-page
    title="New reconciliation"
    heading="New reconciliation"
    subheading="Match books with bank/partner"
    :action="route('admin.reconciliations.store')"
    :cancelUrl="route('admin.reconciliations.index')"
    submitLabel="Create reconciliation">
    @include('admin.reconciliations._form', ['record' => null])
</x-admin.create-page>
