<x-admin.edit-page
    :title="'Edit '.$record->name" heading="Edit bank account" :subheading="$record->bank_name.' '.$record->account_number"
    :action="route('admin.bank-accounts.update', $record)"
    :destroyAction="route('admin.bank-accounts.destroy', $record)"
    :cancelUrl="route('admin.bank-accounts.show', $record)"
    submitLabel="Save changes">
    @include('admin.bank-accounts._form')
</x-admin.edit-page>
