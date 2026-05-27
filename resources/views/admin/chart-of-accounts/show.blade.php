<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="$record->code"
    :backUrl="route('admin.chart-of-accounts.index')"
    :editUrl="route('admin.chart-of-accounts.edit', $record)"
    :fields="[
        'Code'             => $record->code,
        'Name'             => $record->name,
        'Type'             => ucfirst($record->type),
        'Category'         => $record->category,
        'Parent'           => $record->parent->name ?? '—',
        'Opening balance'  => number_format($record->opening_balance, 2).' '.$record->currency,
        'Status'           => $record->is_active ? 'Active' : 'Inactive',
        'Description'      => ['value' => $record->description, 'wide' => true],
        'Created'          => $record->created_at?->format('Y-m-d H:i'),
    ]" />
