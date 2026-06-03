@php($linkedLoan = $record->loan)
<div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Loan record</h3>
            <p class="text-xs text-gray-500 mt-0.5">Created automatically when the application reaches disbursement.</p>
        </div>
        @if ($record->status === 'disbursed' && $linkedLoan)
            <span class="text-xs font-semibold rounded-full px-3 py-1 bg-emerald-100 text-emerald-800">Disbursed</span>
        @elseif ($linkedLoan?->status === 'pending')
            <span class="text-xs font-semibold rounded-full px-3 py-1 bg-amber-100 text-amber-800">Awaiting payout</span>
        @elseif ($linkedLoan?->status === 'active')
            <span class="text-xs font-semibold rounded-full px-3 py-1 bg-emerald-100 text-emerald-800">Active loan</span>
        @endif
    </div>

    @if ($linkedLoan)
        <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm mb-4">
            <div>
                <dt class="text-xs text-gray-500">Loan number</dt>
                <dd class="font-mono font-semibold mt-0.5">{{ $linkedLoan->loan_number }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Principal</dt>
                <dd class="font-semibold mt-0.5">{{ format_money((float) $linkedLoan->principal_amount) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Status</dt>
                <dd class="font-semibold mt-0.5 capitalize">{{ display_label($linkedLoan->status, 'loan_status') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Tenure</dt>
                <dd class="font-semibold mt-0.5">{{ $linkedLoan->tenure_months }} months</dd>
            </div>
        </dl>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.loans.show', $linkedLoan) }}"
               class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg">
                Open loan →
            </a>
            @if ($linkedLoan->status === 'pending')
                <a href="{{ route('admin.loans.disbursement') }}"
                   class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-4 py-2 rounded-lg ring-1 ring-emerald-200">
                    Go to disbursement queue
                </a>
            @endif
        </div>
    @elseif ($record->current_stage === 'disbursement')
        <p class="text-sm text-gray-600 mb-4">No loan record yet. Create one from this approved application.</p>
        <form method="POST" action="{{ route('admin.loan-applications.create-loan', $record) }}">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2.5 rounded-lg shadow-sm">
                Create loan from application
            </button>
        </form>
    @else
        <p class="text-sm text-gray-500">A loan record will be created when this application is marked ready for disbursement.</p>
    @endif
</div>
