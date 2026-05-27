<x-admin.show-page
    :title="trim($record->first_name.' '.$record->last_name)"
    :heading="trim($record->first_name.' '.$record->last_name) ?: 'Guarantor'"
    :subheading="$record->phone"
    :backUrl="route('admin.guarantors.index')"
    :editUrl="route('admin.guarantors.edit', $record)"
    :fields="[
        'First name'   => $record->first_name,
        'Last name'    => $record->last_name,
        'Phone'        => $record->phone,
        'Email'        => $record->email,
        'National ID'  => $record->national_id,
        'Relationship' => ucfirst($record->relationship ?? ''),
        'Address'      => ['value' => $record->address, 'wide' => true],
        'Created'      => $record->created_at?->format('Y-m-d H:i'),
    ]" />
