@php
    $loan = $linkedLoan;
    $section = $section ?? 'owed';
    $servicing = $loan ? app(\App\Services\ActiveLoanServicingService::class)->forLoan($loan) : null;
    $arrearCase = $loan
        ? \App\Models\ArrearCase::query()
            ->where('loan_id', $loan->id)
            ->where('status', 'open')
            ->with([
                'actions' => fn ($q) => $q->with('performer')->latest('performed_at')->limit(10),
                'recoveryAssignments' => fn ($q) => $q->with('vendor')
                    ->whereIn('status', ['assigned', 'in_progress'])
                    ->latest('id'),
            ])
            ->latest('id')
            ->first()
        : null;
    $collectionActions = $arrearCase?->actions ?? collect();
    $recentRepayments = $loan ? $loan->repayments()->latest('paid_at')->limit(8)->get() : collect();
    $openPaymentRequests = $loan
        ? \App\Models\CustomerPayment::query()
            ->where('loan_id', $loan->id)
            ->where('payment_type', 'loan_repayment')
            ->where('status', 'awaiting_payment')
            ->latest('id')
            ->get()
        : collect();
    $restructureRequests = $loan ? $loan->restructureRequests()->latest()->limit(5)->get() : collect();
    $topUpRequests = $loan ? $loan->topUpRequests()->latest()->limit(5)->get() : collect();
    $activeRecovery = null;
    $collateralGps = $loan ? app(\App\Services\GpsDeviceService::class)->collateralForLoan($loan) : [];
    $gpsInstallerContact = $loan ? app(\App\Services\GpsDeviceService::class)->installerContactForLoan($loan) : null;
    $auctionHold = $loan ? app(\App\Services\AuctionHoldService::class)->statusForLoan($loan) : null;
    if ($loan) {
        $loan->loadMissing(['fees', 'repaymentSchedules', 'customer', 'product']);
    }
    $schedule = $loan?->repaymentSchedules ?? collect();
    $sumPrin = (float) $schedule->sum('principal_due');
    $sumInt = (float) $schedule->sum('interest_due');
    $sumTotal = (float) $schedule->sum('total_due');
    $sumPaid = (float) $schedule->sum('amount_paid');
    $today = \Carbon\Carbon::today();
    $penaltyPolicy = $loan ? \App\Services\LoanPenaltyPolicy::for($loan) : null;
    $sectionUrl = function (string $key) use ($workspaceUrl) {
        return $workspaceUrl('facility', $key);
    };
@endphp

@if (! $loan)
    <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-950">
        No loan record is linked to this credit file yet.
    </div>
@else
    <nav class="flex gap-1 overflow-x-auto -mt-1 mb-4" aria-label="Facility sections">
        @foreach ([
            'owed' => 'What they owe',
            'upcoming' => 'Upcoming payments',
            'schedule' => 'Schedule',
            'follow-up' => 'Follow-up',
        ] as $key => $label)
            <a href="{{ $sectionUrl($key) }}"
               @class([
                   'shrink-0 px-3.5 py-2 text-xs font-semibold rounded-xl ring-1 transition',
                   'bg-brand text-white ring-brand' => $section === $key,
                   'bg-white text-gray-700 ring-gray-200 hover:bg-brand-muted/40' => $section !== $key,
               ])
               @if ($section === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @include('admin.loan-applications.review._facility_'.$section)
@endif
