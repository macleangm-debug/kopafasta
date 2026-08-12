@php
    $photoUrls = marketplace_photo_urls($asset['photos'] ?? []);
    $coverPhoto = $photoUrls[0] ?? null;
    $currentStepLabel = collect($steps)->firstWhere('current', true)['label']
        ?? __('borrower.marketplace.next_action');
    $productCode = config('asset_marketplace.asset_loan_product_code', 'AL');
    $applyUrl = route('site.borrower.apply', [
        'product' => $productCode,
        'reservation' => $reservation->id,
    ]);
    $depositDeadlineDays = app(\App\Services\AssetLendingService::class)->depositDeadlineWorkingDays();
@endphp

<x-site.borrower-layout :title="brand_title(__('borrower.marketplace.reserve_title'))" active="marketplace" content-width="narrow">

    <div class="mb-4">
        <a href="{{ route('site.borrower.marketplace.show', $asset['id']) }}" class="text-xs text-gray-500 hover:text-gray-700">← {{ __('borrower.marketplace.back_to_asset') }}</a>
    </div>

    <div class="mb-5">
        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">{{ brand_name() }} · {{ __('borrower.marketplace.reserve_title') }}</p>
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 mt-1">{{ $asset['title'] }}</h1>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif
    @if (session('warning'))
        <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
    @endif

    @include('site.borrower.marketplace._wizard-steps', ['steps' => $steps])

    {{-- Asset summary (wizard card) --}}
    <div class="glass-card overflow-hidden ring-1 ring-brand/10 mb-6">
        <div class="grid sm:grid-cols-5 gap-0">
            <div class="sm:col-span-2 bg-slate-100 aspect-[4/3] sm:aspect-auto sm:min-h-[11rem] relative overflow-hidden">
                @if ($coverPhoto)
                    <img src="{{ $coverPhoto }}" alt="{{ $asset['title'] }}" class="absolute inset-0 w-full h-full object-cover">
                @else
                    <div class="absolute inset-0 grid place-items-center text-xs font-semibold text-gray-400">
                        {{ config('asset_marketplace.categories.'.($asset['category'] ?? 'other')) }}
                    </div>
                @endif
            </div>
            <div class="sm:col-span-3 p-5 sm:p-6">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.marketplace.asset_summary') }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.marketplace.asset_value') }}</dt>
                        <dd class="font-bold text-gray-900 mt-0.5 tabular-nums">{{ format_money($asset['asset_value'] ?? 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.marketplace.loan_amount') }}</dt>
                        <dd class="font-bold text-gray-900 mt-0.5 tabular-nums">{{ format_money($asset['remaining_loan'] ?? 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.marketplace.deposit') }}</dt>
                        <dd class="font-bold text-brand mt-0.5 tabular-nums">{{ format_money($feeBreakdown['deposit'] ?? $asset['deposit'] ?? 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.marketplace.weekly_installment') }}</dt>
                        <dd class="font-bold text-gray-900 mt-0.5 tabular-nums">{{ format_money($asset['weekly_installment'] ?? 0) }}</dd>
                    </div>
                    @if (! empty($asset['max_tenure_months']))
                        <div class="col-span-2">
                            <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.marketplace.duration_range_label') }}</dt>
                            <dd class="font-semibold text-gray-900 mt-0.5">{{ __('borrower.marketplace.duration_range', ['min' => 1, 'max' => (int) $asset['max_tenure_months']]) }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    {{-- Current step action --}}
    <div class="glass-card ring-1 ring-brand/10 p-5 sm:p-6 space-y-4">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.marketplace.next_action') }}</p>
            <h2 class="text-lg font-bold text-gray-900 mt-1">{{ $currentStepLabel }}</h2>
        </div>

        @php
            $app = $reservation->loanApplication;
            $readiness = app(\App\Services\ApplicationDisbursementReadinessService::class);
            $postFeeUrl = $app ? route('site.borrower.application.post-approval-fees', $app->id) : null;
            $contractUrl = $app ? route('site.borrower.application.contract', $app->id) : null;
            $appUrl = $app ? route('site.borrower.application', $app->id) : null;
            $earlyApplyStatuses = [
                'application_started',
                'viewing_scheduled',
                'viewing_completed',
                'interest_confirmed',
                'reservation_fee_paid',
            ];
        @endphp

        @if (in_array($reservation->status, $earlyApplyStatuses, true) && ! $app)
            <p class="text-sm text-gray-600">{{ __('borrower.marketplace.continue_apply_hint') }}</p>
            <a href="{{ $applyUrl }}"
               class="inline-flex w-full sm:w-auto justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-xl text-sm shadow-sm">
                {{ __('borrower.marketplace.start_loan_application') }} →
            </a>
        @elseif ($reservation->status === 'application_submitted')
            <p class="text-sm text-gray-600">{{ __('borrower.marketplace.post_deposit.application_submitted') }}</p>
            @if ($appUrl)
                <a href="{{ $appUrl }}" class="inline-flex w-full sm:w-auto justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-xl text-sm shadow-sm">
                    {{ __('borrower.marketplace.post_deposit.view_application') }} →
                </a>
            @endif
        @elseif ($reservation->status === 'approved' && $reservation->deposit_status !== 'paid')
            <p class="text-sm text-gray-600">{{ __('borrower.marketplace.pay_deposit_after_approval_hint', [
                'amount' => format_number($reservation->deposit_amount),
                'days' => $depositDeadlineDays,
            ]) }}</p>
            <p class="mt-2 text-xs text-gray-500">{{ __('borrower.marketplace.deposit_not_savings', ['brand' => brand_legal_name()]) }}</p>
            @include('site.borrower.marketplace._reservation-payment-form', [
                'step' => 'deposit',
                'amount' => $reservation->deposit_amount,
                'assetId' => $asset['id'],
                'paymentGatewayDummy' => $paymentGatewayDummy ?? payment_gateway_is_dummy(),
                'paymentReference' => $depositRef ?? ('RES-'.$reservation->id.'-DEP'),
                'bankAccounts' => $depositBankAccounts ?? ($bankAccounts ?? []),
                'mobileDetails' => $depositMobileDetails ?? ($mobileDetails ?? null),
                'reservationFeeQuote' => $reservationFeeQuote ?? null,
                'depositQuote' => $depositQuote ?? null,
                'referralWallet' => $referralWallet ?? null,
            ])
        @elseif (in_array($reservation->status, ['deposit_paid', 'approved', 'post_approval_fees_paid', 'gps_installation', 'insurance_active', 'registration_complete'], true))
            @if ($reservation->deposit_status === 'paid' && $app && $readiness->needsPostApprovalFees($app))
                <p class="text-sm text-gray-600">{{ __('borrower.marketplace.post_deposit.fees_pending') }}</p>
                <a href="{{ $postFeeUrl }}" class="inline-flex w-full sm:w-auto justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-xl text-sm shadow-sm">
                    {{ __('borrower.marketplace.post_deposit.pay_fees') }} →
                </a>
            @elseif ($app && $readiness->needsContractSignature($app))
                <p class="text-sm text-gray-600">{{ __('borrower.marketplace.post_deposit.contract_pending') }}</p>
                <a href="{{ $contractUrl }}" class="inline-flex w-full sm:w-auto justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-xl text-sm shadow-sm">
                    {{ __('borrower.marketplace.post_deposit.sign_contract') }} →
                </a>
            @elseif ($app && $readiness->canMarkAssetHandover($app))
                <p class="text-sm text-emerald-800 font-medium">{{ __('borrower.marketplace.post_deposit.handover_ready') }}</p>
                @if ($appUrl)
                    <a href="{{ $appUrl }}" class="inline-flex w-full sm:w-auto justify-center bg-brand hover:bg-brand-light text-white font-bold px-6 py-3 rounded-xl text-sm shadow-sm">
                        {{ __('borrower.marketplace.post_deposit.view_application') }} →
                    </a>
                @endif
            @else
                <p class="text-sm text-gray-600">{{ __('borrower.marketplace.post_deposit.readiness') }}</p>
                @if ($appUrl)
                    <a href="{{ $appUrl }}" class="inline-flex w-full sm:w-auto justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-xl text-sm shadow-sm">
                        {{ __('borrower.marketplace.post_deposit.view_application') }} →
                    </a>
                @endif
            @endif
        @elseif ($reservation->status === 'released')
            @php $loan = $reservation->loanApplication?->loan; @endphp
            <p class="text-sm text-emerald-800 font-medium">{{ __('borrower.marketplace.post_deposit.handed_over') }}</p>
            @if ($loan)
                <a href="{{ route('site.borrower.loans.show', $loan->id) }}" class="inline-flex w-full sm:w-auto justify-center bg-brand hover:bg-brand-light text-white font-bold px-6 py-3 rounded-xl text-sm shadow-sm">
                    {{ __('borrower.marketplace.post_deposit.view_loan') }} →
                </a>
            @endif
        @else
            <p class="text-sm text-emerald-700 font-semibold">{{ __('borrower.marketplace.in_progress') }}</p>
            <a href="{{ route('site.borrower.loans') }}" class="text-sm font-semibold text-brand hover:underline inline-block">{{ __('borrower.dashboard.view_all') }}</a>
        @endif
    </div>

</x-site.borrower-layout>
