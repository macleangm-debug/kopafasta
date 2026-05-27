<x-admin.create-page
    title="New suspicious activity" heading="New suspicious activity" subheading="File a suspicious transaction report"
    :action="route('admin.suspicious-activities.store')" :cancelUrl="route('admin.suspicious-activities.index')"
    submitLabel="File report">
    @include('admin.suspicious-activities._form', ['record' => null])
</x-admin.create-page>
