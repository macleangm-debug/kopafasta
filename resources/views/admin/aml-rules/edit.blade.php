<x-admin.edit-page
    :title="'Edit '.$record->name" heading="Edit AML rule" :subheading="$record->code"
    :action="route('admin.aml-rules.update', $record)"
    :destroyAction="route('admin.aml-rules.destroy', $record)"
    :cancelUrl="route('admin.aml-rules.show', $record)"
    submitLabel="Save changes">
    @include('admin.aml-rules._form')
</x-admin.edit-page>
