<x-admin.edit-page
    :title="'Edit '.$record->factor" heading="Edit risk rule" :subheading="$record->factor.' '.$record->operator.' '.$record->value"
    :action="route('admin.risk-scoring-rules.update', $record)"
    :destroyAction="route('admin.risk-scoring-rules.destroy', $record)"
    :cancelUrl="route('admin.risk-scoring-rules.show', $record)"
    submitLabel="Save changes">
    @include('admin.risk-scoring-rules._form')
</x-admin.edit-page>
