<x-admin.create-page
    title="New vendor"
    heading="New vendor"
    subheading="Add a service partner"
    :action="route('admin.vendors.store')"
    :cancelUrl="route('admin.vendors.index')"
    submitLabel="Create vendor">
    @include('admin.vendors._form', ['record' => null])
</x-admin.create-page>
