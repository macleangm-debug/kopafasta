<x-admin.show-page
    :title="'Expense '.($record->reference ?? '#'.$record->id)"
    :heading="$record->category ?? 'Expense'"
    :subheading="$record->reference"
    :backUrl="route('admin.expenses.index')"
    :editUrl="route('admin.expenses.edit', $record)"
    :fields="[
        'Branch'         => optional(\App\Models\Branch::find($record->branch_id))->name,
        'Vendor'         => optional(\App\Models\Vendor::find($record->vendor_id))->name,
        'Category'       => $record->category,
        'Status'         => ucfirst($record->status ?? ''),
        'Amount'         => $record->amount !== null ? (($record->currency ?? 'TZS').' '.number_format((float) $record->amount)) : null,
        'Expense date'   => optional($record->expense_date)->format('Y-m-d'),
        'Payment method' => ucfirst(str_replace('_', ' ', $record->payment_method ?? '')),
        'Reference'      => $record->reference,
        'Description'    => ['value' => $record->description, 'wide' => true],
        'Created'        => $record->created_at?->format('Y-m-d H:i'),
    ]" />
