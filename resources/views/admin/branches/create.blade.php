<x-admin.create-page
    title="New branch"
    heading="New branch"
    subheading="Add a physical or virtual branch"
    :action="route('admin.branches.store')"
    :cancelUrl="route('admin.branches.index')"
    submitLabel="Create branch">
    @include('admin.branches._form', ['record' => null])
</x-admin.create-page>
