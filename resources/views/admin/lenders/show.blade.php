<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->code"
    :backUrl="route('admin.lenders.index')"
    :editUrl="route('admin.lenders.edit', $record)"
    :fields="[
        'Code'           => $record->code,
        'Type'           => ucfirst($record->type ?? ''),
        'Status'         => ucfirst($record->status ?? ''),
        'Contact'        => $record->contact_person,
        'Phone'          => $record->phone,
        'Email'          => $record->email,
        'Credit limit'   => $record->credit_limit ? 'TZS '.number_format((float) $record->credit_limit) : null,
        'Address'        => ['value' => $record->address, 'wide' => true],
        'Created'        => $record->created_at?->format('Y-m-d H:i'),
    ]" />
