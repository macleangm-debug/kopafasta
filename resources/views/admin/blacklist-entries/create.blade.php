<x-admin.create-page
    title="New blacklist entry" heading="New blacklist entry" subheading="Block an identifier"
    :action="route('admin.blacklist-entries.store')" :cancelUrl="route('admin.blacklist-entries.index')"
    submitLabel="Add entry">
    @include('admin.blacklist-entries._form', ['record' => null])
</x-admin.create-page>
