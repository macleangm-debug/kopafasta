<x-admin.create-page title="New marketplace asset" heading="New marketplace asset" subheading="Add asset lending inventory"
    :action="route('admin.marketplace-assets.store')" :cancelUrl="route('admin.marketplace-assets.index')" submitLabel="Create asset">
    @include('admin.marketplace-assets._form', ['record' => null])
</x-admin.create-page>
