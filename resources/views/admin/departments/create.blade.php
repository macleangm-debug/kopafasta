<x-admin.create-page
    title="New department" heading="New department" subheading="Add an org unit"
    :action="route('admin.departments.store')" :cancelUrl="route('admin.departments.index')"
    submitLabel="Create department">
    @include('admin.departments._form', ['record' => null])
</x-admin.create-page>
