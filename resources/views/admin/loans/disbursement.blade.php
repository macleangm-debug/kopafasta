<x-admin.layout title="Disbursement Queue" heading="" subheading="">
    <x-admin.letterhead kicker="Credit management" title="Disbursement queue" subtitle="Approved applications with pending loan payout" />
@php
        $appsAwaitingLoan = \App\Models\LoanApplication::query()
            ->with(['customer', 'product'])
            ->where('current_stage', 'disbursement')
            ->whereDoesntHave('loan')
            ->latest()
            ->limit(20)
            ->get();
    @endphp

    @if ($appsAwaitingLoan->isNotEmpty())
        <div class="mb-6 bg-amber-50 rounded-xl ring-1 ring-amber-200 p-5">
            <h2 class="text-sm font-semibold text-amber-950 mb-1">Applications without a loan record</h2>
            <p class="text-xs text-amber-900/80 mb-4">These were approved before auto-origination was enabled. Create the loan, then disburse below.</p>
            <ul class="space-y-2">
                @foreach ($appsAwaitingLoan as $app)
                    <li class="flex flex-wrap items-center justify-between gap-3 bg-white/70 rounded-lg px-4 py-3 text-sm">
                        <div>
                            <span class="font-mono text-xs text-gray-500">{{ $app->application_number }}</span>
                            <span class="font-medium text-gray-900 ml-2">{{ trim($app->customer?->first_name.' '.$app->customer?->last_name) }}</span>
                            <span class="text-gray-500">· {{ format_money((float) ($app->recommended_amount ?: $app->requested_amount)) }}</span>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.loan-applications.show', $app) }}" class="text-xs font-semibold text-gray-600 hover:text-gray-900">Application</a>
                            <form method="POST" action="{{ route('admin.loan-applications.create-loan', $app) }}">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-brand hover:text-brand-light">Create loan →</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="text-sm text-gray-600 mb-4">Pending loans ready for payout. Click <strong>Disburse</strong> on a loan to activate it and generate the repayment schedule.</p>

    @livewire('admin.loans-table', ['status' => 'pending', 'lockStatus' => true])
</x-admin.layout>
