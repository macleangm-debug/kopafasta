@php
    $customer = \App\Models\Customer::find($record->customer_id);
    $product  = \App\Models\LoanProduct::find($record->loan_product_id);
    $branch   = \App\Models\Branch::find($record->branch_id);
    $offer    = \App\Models\LoanAgreement::where('loan_application_id', $record->id)
        ->where('document_type', 'offer_letter')
        ->latest('id')->first();
    $affordability = $record->credit_appraisal_payload['affordability'] ?? null;
@endphp
<x-admin.show-page
    :title="$record->application_number"
    :heading="$record->application_number ?: 'Application'"
    :subheading="$customer ? trim($customer->first_name.' '.$customer->last_name) : null"
    :backUrl="route('admin.loan-applications.index')"
    :editUrl="route('admin.loan-applications.edit', $record)"
    :fields="[
        'Application #'     => $record->application_number,
        'Status'            => ucfirst(str_replace('_', ' ', $record->status ?? '')),
        'Customer'          => $customer ? trim($customer->first_name.' '.$customer->last_name) : null,
        'Loan product'      => $product?->name,
        'Branch'            => $branch?->name,
        'Current stage'     => $record->current_stage,
        'Requested amount'  => $record->requested_amount !== null ? 'TZS '.number_format((float) $record->requested_amount) : null,
        'Tenure (months)'   => $record->requested_tenure_months,
        'Recommended amount' => $record->recommended_amount !== null ? 'TZS '.number_format((float) $record->recommended_amount) : null,
        'Purpose'           => ['value' => $record->purpose, 'wide' => true],
        'Rejection reason'  => ['value' => $record->rejection_reason, 'wide' => true],
        'Created'           => $record->created_at?->format('Y-m-d H:i'),
    ]">

<div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-700">Offer letter</h3>
        @if (session('status'))
            <span class="text-xs text-emerald-700">{{ session('status') }}</span>
        @endif
    </div>

    @if ($offer)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div><div class="text-xs uppercase text-gray-500">Reference</div><div class="font-mono">{{ $offer->reference }}</div></div>
            <div><div class="text-xs uppercase text-gray-500">Status</div>
                <span @class([
                    'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase',
                    'bg-emerald-100 text-emerald-800' => $offer->status === 'signed',
                    'bg-amber-100 text-amber-800'     => $offer->status === 'sent',
                    'bg-gray-100 text-gray-700'       => in_array($offer->status, ['draft','expired','cancelled']),
                ])>{{ $offer->status }}</span>
            </div>
            <div><div class="text-xs uppercase text-gray-500">Signed at</div><div>{{ optional($offer->signed_at)->format('Y-m-d H:i') ?? '—' }}</div></div>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($offer->file_path) }}" target="_blank"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 px-4 py-2 rounded-lg">
                View PDF
            </a>
            <form method="POST" action="{{ route('admin.loan-applications.agreement.generate', $record) }}"
                  onsubmit="return confirm('Regenerate the offer letter? The borrower will need to sign the new version.');">
                @csrf
                <button class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 px-4 py-2 rounded-lg">
                    Regenerate
                </button>
            </form>
        </div>
    @else
        <p class="text-sm text-gray-500 mb-3">No offer letter has been issued yet for this application.</p>
        <form method="POST" action="{{ route('admin.loan-applications.agreement.generate', $record) }}">
            @csrf
            <button class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg">
                Generate offer letter
            </button>
        </form>
    @endif
</div>

@if ($affordability)
<div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-700">Affordability appraisal</h3>
        <span @class([
            'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase',
            'bg-emerald-100 text-emerald-800' => $affordability['verdict'] === 'pass',
            'bg-amber-100 text-amber-800'     => $affordability['verdict'] === 'warn',
            'bg-red-100 text-red-800'         => $affordability['verdict'] === 'fail',
        ])>{{ $affordability['verdict'] }}</span>
    </div>
    <p class="text-sm text-gray-600 mb-4">{{ $affordability['reason'] ?? '' }}</p>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><div class="text-xs uppercase text-gray-500">Monthly income</div><div class="font-semibold">TZS {{ number_format($affordability['net_income'] ?? 0) }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">Existing obligations</div><div class="font-semibold">TZS {{ number_format($affordability['existing_obligations'] ?? 0) }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">New EMI</div><div class="font-semibold">TZS {{ number_format($affordability['new_emi'] ?? 0) }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">DSR / Limit</div><div class="font-semibold">{{ number_format(($affordability['dsr'] ?? 0) * 100, 1) }}% / {{ number_format(($affordability['threshold'] ?? 0) * 100, 1) }}%</div></div>
    </div>
    @if (! empty($affordability['evaluated_at']))
        <p class="mt-3 text-xs text-gray-400">Evaluated {{ \Illuminate\Support\Carbon::parse($affordability['evaluated_at'])->diffForHumans() }}</p>
    @endif
</div>
@endif

</x-admin.show-page>
