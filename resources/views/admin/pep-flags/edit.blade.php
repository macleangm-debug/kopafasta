<x-admin.edit-page
    :title="'Edit '.$record->full_name" heading="Edit PEP flag" :subheading="$record->full_name"
    :action="route('admin.pep-flags.update', $record)"
    :destroyAction="route('admin.pep-flags.destroy', $record)"
    :cancelUrl="route('admin.pep-flags.show', $record)"
    submitLabel="Save changes">
    @include('admin.pep-flags._form')
</x-admin.edit-page>
