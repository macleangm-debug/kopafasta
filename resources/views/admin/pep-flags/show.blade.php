<x-admin.show-page
    :title="$record->full_name" :heading="$record->full_name" :subheading="$record->position ?? '—'"
    :backUrl="route('admin.pep-flags.index')"
    :editUrl="route('admin.pep-flags.edit', $record)"
    :fields="[
        'Full name'    => $record->full_name,
        'Position'     => $record->position,
        'Organization' => $record->organization,
        'Category'     => display_label($record->category, 'pep_category'),
        'Risk level'   => ucfirst($record->risk_level),
        'Listed on'    => optional($record->listed_on)->format('Y-m-d') ?? '—',
        'Linked customer' => $record->customer ? trim(($record->customer->first_name ?? '').' '.($record->customer->last_name ?? '')) : '—',
        'Status'       => $record->is_active ? 'Flagged' : 'Cleared',
        'Notes'        => ['value' => $record->notes, 'wide' => true],
    ]" />
