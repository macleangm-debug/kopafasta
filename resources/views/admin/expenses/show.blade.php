<x-admin.show-page
    :title="'Expense '.($record->reference ?? '#'.$record->id)"
    :heading="$record->category ?? 'Expense'"
    :subheading="$record->reference"
    :backUrl="route('admin.expenses.index')"
    :editUrl="route('admin.expenses.edit', $record)"
    :fields="[
        'Branch'         => optional(\App\Models\Branch::find($record->branch_id))->name,
        'Partner'         => optional(\App\Models\Vendor::find($record->vendor_id))->name,
        'Category'       => $record->category,
        'Status'         => ucfirst($record->status ?? ''),
        'Amount'         => $record->amount !== null ? format_money($record->amount, true, 2) : null,
        'Expense date'   => optional($record->expense_date)->format('Y-m-d'),
        'Payment method' => display_label($record->payment_method, 'payment_method'),
        'Reference'      => $record->reference,
        'Description'    => ['value' => $record->description, 'wide' => true],
        'Created'        => $record->created_at?->format('Y-m-d H:i'),
    ]" />
