<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->vendor_number"
    :backUrl="route('admin.vendors.index')"
    :editUrl="route('admin.vendors.edit', $record)"
    :fields="[
        'Vendor #'  => $record->vendor_number,
        'Name'      => $record->name,
        'Category'  => $record->category,
        'Status'    => ucfirst($record->status ?? ''),
        'Phone'     => $record->phone,
        'Email'     => $record->email,
        'Address'   => ['value' => $record->address, 'wide' => true],
        'Created'   => $record->created_at?->format('Y-m-d H:i'),
    ]" />
