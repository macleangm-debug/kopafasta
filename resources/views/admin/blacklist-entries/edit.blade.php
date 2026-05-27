<x-admin.edit-page
    :title="'Edit '.$record->identifier_value" heading="Edit blacklist entry" :subheading="$record->identifier_type.': '.$record->identifier_value"
    :action="route('admin.blacklist-entries.update', $record)"
    :destroyAction="route('admin.blacklist-entries.destroy', $record)"
    :cancelUrl="route('admin.blacklist-entries.show', $record)"
    submitLabel="Save changes">
    @include('admin.blacklist-entries._form')
</x-admin.edit-page>
