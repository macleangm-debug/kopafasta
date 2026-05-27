<x-admin.create-page
    title="New notification template" heading="New notification template" subheading="SMS / email / push body"
    :action="route('admin.notification-templates.store')" :cancelUrl="route('admin.notification-templates.index')"
    submitLabel="Create template">
    @include('admin.notification-templates._form', ['record' => null])
</x-admin.create-page>
