<x-admin.create-page
    title="New settlement"
    heading="New settlement"
    subheading="Record a partner settlement"
    :action="route('admin.settlements.store')"
    :cancelUrl="route('admin.settlements.index')"
    submitLabel="Create settlement">
    @include('admin.settlements._form', ['record' => null])
</x-admin.create-page>
