<x-admin.create-page
    title="New ticket"
    heading="New support ticket"
    subheading="Log a customer issue"
    :action="route('admin.support-tickets.store')"
    :cancelUrl="route('admin.support-tickets.index')"
    submitLabel="Create ticket">
    @include('admin.support-tickets._form', ['record' => null])
</x-admin.create-page>
