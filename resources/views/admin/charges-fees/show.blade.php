@php
    $whenLabels = [
        'application'   => 'On application submit',
        'post_approval' => 'After approval (before disbursement)',
        'disbursement'  => 'At disbursement',
        'late'          => 'When instalment is late',
        'event'         => 'On specific event (early settle / restructure)',
    ];
    $when = $whenLabels[$record->charge_when] ?? $record->charge_when;
@endphp
<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="$record->code"
    :backUrl="route('admin.charges-fees.index')"
    :editUrl="route('admin.charges-fees.edit', $record)"
    :fields="[
        'Code' => $record->code,
        'Name' => $record->name,
        'Type'  => display_label($record->type, 'charge_type'),
        'Basis' => display_label($record->basis, 'charge_basis'),
        'Amount / rate' => ['value' => $record->amount, 'numeric' => true, 'decimals' => 4],
        'Min amount' => $record->min_amount ? format_number($record->min_amount, 2) : '—',
        'Max amount' => $record->max_amount ? format_number($record->max_amount, 2) : '—',
        'When charged' => $when,
        'GL account' => $record->glAccount->name ?? '—',
        'Status' => $record->is_active ? 'Active' : 'Inactive',
        'Description' => ['value' => $record->description, 'wide' => true],
    ]">
    <div class="mt-4 rounded-2xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3 text-sm text-gray-700">
        Fee catalog is managed from <a href="{{ route('admin.settings.index') }}" class="font-semibold text-brand hover:underline">Settings hub → Finance → Charges & fees</a>.
        Loan products choose which post-approval fees apply. Membership fee itself is under Settings → Membership (not REG_POST_FEE).
    </div>
</x-admin.show-page>
