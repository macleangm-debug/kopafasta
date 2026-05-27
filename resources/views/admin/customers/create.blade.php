<x-admin.create-page
    title="New customer"
    heading="New customer"
    subheading="Add a new customer record"
    :action="route('admin.customers.store')"
    :cancelUrl="route('admin.customers.index')"
    submitLabel="Create customer">
    @include('admin.customers._form', ['record' => null])
</x-admin.create-page>
