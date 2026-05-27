<x-admin.edit-page
    :title="'Edit '.$record->name" heading="Edit document template" :subheading="$record->code"
    :action="route('admin.document-templates.update', $record)"
    :destroyAction="route('admin.document-templates.destroy', $record)"
    :cancelUrl="route('admin.document-templates.show', $record)"
    submitLabel="Save changes">
    @include('admin.document-templates._form')
</x-admin.edit-page>
