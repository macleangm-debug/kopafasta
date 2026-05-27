@php $loan = \App\Models\Loan::find($record->loan_id); @endphp
<x-admin.show-page
    :title="$record->reference"
    :heading="$record->reference ?: 'Repayment'"
    :subheading="$loan?->loan_number"
    :backUrl="route('admin.repayments.index')"
    :editUrl="route('admin.repayments.edit', $record)"
    :fields="[
        'Reference'  => $record->reference,
        'Loan'       => $loan?->loan_number,
        'Status'     => ucfirst($record->status ?? ''),
        'Channel'    => ucfirst(str_replace('_', ' ', $record->channel ?? '')),
        'Amount'     => $record->amount !== null ? 'TZS '.number_format((float) $record->amount) : null,
        'Principal'  => $record->principal_component !== null ? number_format((float) $record->principal_component) : null,
        'Interest'   => $record->interest_component  !== null ? number_format((float) $record->interest_component)  : null,
        'Penalty'    => $record->penalty_component   !== null ? number_format((float) $record->penalty_component)   : null,
        'Paid at'    => optional($record->paid_at)->format('Y-m-d H:i'),
        'Schedule #' => $record->repayment_schedule_id,
        'Created'    => $record->created_at?->format('Y-m-d H:i'),
    ]" />
