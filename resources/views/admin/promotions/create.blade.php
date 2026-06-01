<x-admin.create-page
    title="New campaign"
    heading="New campaign"
    subheading="Configure a promotional campaign"
    :action="route('admin.promotions.store')"
    :cancelUrl="route('admin.promotions.index')"
    submitLabel="Create campaign">
    @include('admin.promotions._form', ['record' => null])
</x-admin.create-page>
