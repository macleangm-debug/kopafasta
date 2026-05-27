<x-admin.create-page
    title="New lender"
    heading="New lender"
    subheading="Add a capital partner"
    :action="route('admin.lenders.store')"
    :cancelUrl="route('admin.lenders.index')"
    submitLabel="Create lender">
    @include('admin.lenders._form', ['record' => null])
</x-admin.create-page>
