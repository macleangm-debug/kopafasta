@php
    $gl = $record->glAccount ?? ($record->gl_account_id ? \App\Models\ChartOfAccount::find($record->gl_account_id) : null);
@endphp

<x-admin.show-page
    :title="'Expense '.($record->reference ?? '#'.$record->id)"
    :heading="$record->category ?? 'Expense'"
    :subheading="$record->reference"
    :backUrl="route('admin.expenses.index')"
    :editUrl="route('admin.expenses.edit', $record)"
    :fields="[
        'Branch'         => optional(\App\Models\Branch::find($record->branch_id))->name,
        'Partner'         => optional(\App\Models\Vendor::find($record->vendor_id))->name,
        'Category'       => ucfirst(str_replace('_', ' ', (string) $record->category)),
        'GL account'     => $gl ? ($gl->code.' · '.$gl->name) : null,
        'Status'         => ucfirst($record->status ?? ''),
        'Amount'         => $record->amount !== null ? format_money($record->amount, true, 2) : null,
        'Expense date'   => optional($record->expense_date)->format('Y-m-d'),
        'Payment method' => display_label($record->payment_method, 'payment_method'),
        'Reference'      => $record->reference,
        'Journal posted' => optional($record->journal_posted_at)->format('Y-m-d H:i'),
        'Description'    => ['value' => $record->description, 'wide' => true],
        'Created'        => $record->created_at?->format('Y-m-d H:i'),
    ]">
    @if (($record->status ?? '') === 'paid' && ! $record->journal_posted_at)
        <div class="mt-4">
            <form method="POST" action="{{ route('admin.expenses.post', $record) }}">
                @csrf
                <button type="submit" class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2 rounded-lg">
                    Post to journal
                </button>
            </form>
        </div>
    @endif
</x-admin.show-page>
