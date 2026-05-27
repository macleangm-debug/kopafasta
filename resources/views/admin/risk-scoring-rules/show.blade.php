<x-admin.show-page
    :title="$record->factor" :heading="$record->factor" :subheading="$record->operator.' '.$record->value"
    :backUrl="route('admin.risk-scoring-rules.index')"
    :editUrl="route('admin.risk-scoring-rules.edit', $record)"
    :fields="[
        'Factor' => $record->factor, 'Operator' => $record->operator,
        'Value' => $record->value, 'Weight' => $record->weight,
        'Category' => $record->category,
        'Status' => $record->is_active ? 'Active' : 'Inactive',
    ]" />
