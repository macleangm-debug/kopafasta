<x-admin.create-page
    title="New write-off rule" heading="New write-off rule" subheading="Define write-off trigger"
    :action="route('admin.write-off-rules.store')" :cancelUrl="route('admin.write-off-rules.index')"
    submitLabel="Create rule">
    @include('admin.write-off-rules._form', ['record' => null])
</x-admin.create-page>
