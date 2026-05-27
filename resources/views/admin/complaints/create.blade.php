<x-admin.create-page
    title="New complaint"
    heading="New complaint"
    subheading="Log a formal complaint"
    :action="route('admin.complaints.store')"
    :cancelUrl="route('admin.complaints.index')"
    submitLabel="Create complaint">
    @include('admin.complaints._form', ['record' => null])
</x-admin.create-page>
