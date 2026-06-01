<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="display_label($record->provider, 'mobile_provider')"
    :backUrl="route('admin.mobile-money-accounts.index')"
    :editUrl="route('admin.mobile-money-accounts.edit', $record)"
    :fields="[
        'Display name' => $record->name,
        'Provider'     => display_label($record->provider, 'mobile_provider'),
        'MSISDN'       => $record->msisdn,
        'Paybill'      => $record->paybill_number,
        'Till'         => $record->till_number,
        'Environment'  => $record->environment,
        'Opening balance' => number_format($record->opening_balance, 2),
        'GL account'   => $record->glAccount->name ?? '—',
        'Purpose'      => ucfirst($record->purpose),
        'Status'       => $record->is_active ? 'Active' : 'Inactive',
    ]" />
