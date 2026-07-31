<x-admin.create-page
    title="New partner"
    heading="New partner"
    subheading="Add a service partner"
    :action="route('admin.partners.store')"
    :cancelUrl="route('admin.partners.all')"
    submitLabel="Create partner"
    enctype="multipart/form-data">
    @include('admin.partners._form', ['record' => null])
</x-admin.create-page>
