<x-admin.create-page
    title="New funding pool"
    heading="New funding pool"
    subheading="Allocate lender capital"
    :action="route('admin.funding-pools.store')"
    :cancelUrl="route('admin.funding-pools.index')"
    submitLabel="Create pool">
    @include('admin.funding-pools._form', ['record' => null])
</x-admin.create-page>
