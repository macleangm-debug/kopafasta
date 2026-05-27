<x-admin.show-page
    :title="'Reconciliation #'.$record->id"
    :heading="'Reconciliation #'.$record->id"
    :subheading="optional($record->period_start)->format('Y-m-d').' → '.optional($record->period_end)->format('Y-m-d')"
    :backUrl="route('admin.reconciliations.index')"
    :editUrl="route('admin.reconciliations.edit', $record)"
    :fields="[
        'Settlement'    => optional(\App\Models\Settlement::find($record->settlement_id))->reference,
        'Status'        => ucfirst($record->status ?? ''),
        'Period start'  => optional($record->period_start)->format('Y-m-d'),
        'Period end'    => optional($record->period_end)->format('Y-m-d'),
        'System total'  => $record->system_total !== null ? number_format((float) $record->system_total) : null,
        'Bank total'    => $record->bank_total   !== null ? number_format((float) $record->bank_total)   : null,
        'Variance'      => $record->variance     !== null ? number_format((float) $record->variance)     : null,
        'Reconciled at' => optional($record->reconciled_at)->format('Y-m-d'),
        'Notes'         => ['value' => $record->notes, 'wide' => true],
        'Created'       => $record->created_at?->format('Y-m-d H:i'),
    ]" />
