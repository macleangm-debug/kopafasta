<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->code"
    :backUrl="route('admin.promotions.index')"
    :editUrl="route('admin.promotions.edit', $record)"
    :fields="[
        'Code' => $record->code,
        'Type' => str_replace('_', ' ', $record->type),
        'Status' => ucfirst($record->status),
        'Applies to' => $record->applies_to ? str_replace('_', ' ', $record->applies_to) : '—',
        'Discount' => $record->discount_percent ? format_number((float) $record->discount_percent, 2).'%' : ($record->discount_amount ? 'TZS '.format_number((float) $record->discount_amount) : '—'),
        'Period' => (optional($record->starts_at)->format('d M Y') ?? '—').' → '.(optional($record->ends_at)->format('d M Y') ?? '—'),
        'Message template' => ['value' => $record->message_template, 'wide' => true],
    ]"
/>
