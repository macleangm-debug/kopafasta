<x-admin.create-page
    title="New approval limit" heading="New approval limit" subheading="Authority delegation"
    :action="route('admin.approval-limits.store')" :cancelUrl="route('admin.approval-limits.index')"
    submitLabel="Create limit">
    @include('admin.approval-limits._form', ['record' => null])
</x-admin.create-page>
