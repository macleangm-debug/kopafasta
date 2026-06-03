<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="$record->bank_name"
    :backUrl="route('admin.bank-accounts.index')"
    :editUrl="route('admin.bank-accounts.edit', $record)"
    :fields="[
        'Display name'    => $record->name,
        'Bank'            => $record->bank_name,
        'Account number'  => $record->account_number,
        'Branch'          => $record->branch,
        'SWIFT/BIC'       => $record->swift_code,
        'Currency'        => $record->currency,
        'Opening balance' => format_number($record->opening_balance, 2),
        'Purpose'         => ucfirst($record->purpose),
        'GL account'      => $record->glAccount->name ?? '—',
        'Status'          => $record->is_active ? 'Active' : 'Inactive',
        'Notes'           => ['value' => $record->notes, 'wide' => true],
    ]" />
