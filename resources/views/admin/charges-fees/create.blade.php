<x-admin.create-page
    title="New fee" heading="New fee" subheading="Define a charge or fee"
    :action="route('admin.charges-fees.store')" :cancelUrl="route('admin.charges-fees.index')"
    submitLabel="Create fee">
    @include('admin.charges-fees._form', ['record' => null])
</x-admin.create-page>
