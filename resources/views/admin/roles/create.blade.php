<x-admin.create-page
    title="New role" heading="New role" subheading="Define a staff role"
    :action="route('admin.roles.store')" :cancelUrl="route('admin.roles.index')"
    submitLabel="Create role">
    @include('admin.roles._form', ['record' => null])
</x-admin.create-page>
