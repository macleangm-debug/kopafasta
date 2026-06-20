@php
    $loan = \App\Models\Loan::find($record->loan_id);
    $canApprove = repayment_approval_required()
        && $record->status === 'pending'
        && auth('admin')->user()?->hasPermission('finance.operations')
        && (int) $record->recorded_by !== (int) auth('admin')->id();
@endphp
<x-admin.show-page
    :title="$record->reference"
    :heading="$record->reference ?: 'Repayment'"
    :subheading="$loan?->loan_number"
    :backUrl="route('admin.repayments.index')"
    :editUrl="$record->status === 'pending' && repayment_approval_required() ? null : route('admin.repayments.edit', $record)"
    :fields="[
        'Reference'  => $record->reference,
        'Loan'       => $loan?->loan_number,
        'Status'     => ucfirst($record->status ?? ''),
        'Channel'    => display_label($record->channel, 'channel'),
        'Amount'     => $record->amount !== null ? 'TZS '.format_number((float) $record->amount) : null,
        'Principal'  => $record->principal_component !== null ? format_number((float) $record->principal_component) : null,
        'Interest'   => $record->interest_component  !== null ? format_number((float) $record->interest_component)  : null,
        'Penalty'    => $record->penalty_component   !== null ? format_number((float) $record->penalty_component)   : null,
        'Paid at'    => optional($record->paid_at)->format('Y-m-d H:i'),
        'Recorded by'=> $record->recorded_by ? (\App\Models\User::find($record->recorded_by)?->name ?? '#'.$record->recorded_by) : null,
        'Approved by'=> $record->approved_by ? (\App\Models\User::find($record->approved_by)?->name ?? '#'.$record->approved_by) : null,
        'Approved at'=> optional($record->approved_at)->format('Y-m-d H:i'),
        'Schedule #' => $record->repayment_schedule_id,
        'Created'    => $record->created_at?->format('Y-m-d H:i'),
    ]">
    @if ($canApprove)
        <div class="mt-4 flex justify-end">
            <form method="POST" action="{{ route('admin.repayments.approve', $record) }}"
                  onsubmit="return confirm('Approve and post this repayment to the ledger?');">
                @csrf
                <button type="submit" class="inline-flex text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg">
                    Approve & post to ledger
                </button>
            </form>
        </div>
    @elseif ($record->status === 'pending' && repayment_approval_required())
        <div class="mt-4 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
            Awaiting supervisor approval before this repayment is posted. The recorder cannot approve their own entry.
        </div>
    @endif
</x-admin.show-page>
