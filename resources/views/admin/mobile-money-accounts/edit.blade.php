<x-admin.edit-page
    :title="'Edit '.$record->name" heading="Edit mobile money account" :subheading="$record->msisdn"
    :action="route('admin.mobile-money-accounts.update', $record)"
    :destroyAction="route('admin.mobile-money-accounts.destroy', $record)"
    :cancelUrl="route('admin.mobile-money-accounts.show', $record)"
    submitLabel="Save changes">
    @include('admin.mobile-money-accounts._form')
</x-admin.edit-page>
