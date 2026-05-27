<x-admin.edit-page
    :title="'Edit reconciliation #'.$record->id"
    heading="Edit reconciliation"
    :subheading="optional($record->period_start)->format('Y-m-d').' → '.optional($record->period_end)->format('Y-m-d')"
    :action="route('admin.reconciliations.update', $record)"
    :destroyAction="route('admin.reconciliations.destroy', $record)"
    :cancelUrl="route('admin.reconciliations.show', $record)"
    submitLabel="Save changes">
    @include('admin.reconciliations._form')
</x-admin.edit-page>
