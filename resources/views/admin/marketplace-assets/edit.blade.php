<x-admin.edit-page :title="'Edit '.$record->title" :heading="$record->title" subheading="Update marketplace asset"
    :action="route('admin.marketplace-assets.update', $record)" :cancelUrl="route('admin.marketplace-assets.show', $record)" submitLabel="Save changes">
    @include('admin.marketplace-assets._form', ['record' => $record])
</x-admin.edit-page>
