{{-- Shared toolbar for loan list pages. Loans originate from approved applications. --}}
@props(['showManualCreate' => false])

<div class="mb-4 rounded-lg bg-slate-50 ring-1 ring-slate-200 px-4 py-3 text-sm text-slate-800">
    <strong>Loans come from applications.</strong>
    Review and approve under <a href="{{ route('admin.loan-applications.index') }}" class="font-semibold text-amber-700 hover:text-amber-800">Applications</a>,
    then disburse approved records from the <a href="{{ route('admin.loans.disbursement') }}" class="font-semibold text-amber-700 hover:text-amber-800">Disbursement queue</a>.
</div>

<div class="flex flex-wrap items-center justify-end gap-2 mb-4">
    <a href="{{ route('admin.loan-applications.pipeline.approved') }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 px-4 py-2 rounded-lg ring-1 ring-gray-300 transition">
        Review applications
    </a>
    <a href="{{ route('admin.loans.disbursement') }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg shadow-sm transition">
        Disbursement queue
    </a>
    @if ($showManualCreate)
        <a href="{{ route('admin.loans.create') }}"
           class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900 px-3 py-2">
            Manual loan wizard
        </a>
    @endif
</div>
