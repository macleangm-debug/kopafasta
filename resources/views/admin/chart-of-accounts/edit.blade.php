<x-admin.edit-page
    :title="'Edit '.$record->name" heading="Edit GL account" :subheading="$record->code"
    :action="route('admin.chart-of-accounts.update', $record)"
    :destroyAction="route('admin.chart-of-accounts.destroy', $record)"
    :cancelUrl="route('admin.chart-of-accounts.show', $record)"
    submitLabel="Save changes">
    @include('admin.chart-of-accounts._form')
</x-admin.edit-page>
