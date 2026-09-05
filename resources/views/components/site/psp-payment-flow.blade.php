@props([
    'payment',
    'bankAccounts' => [],
    'mobileDetails' => [],
    'showPromo' => false,
    'quote' => null,
    'promoValue' => null,
    'walletReward' => null,
    'formAction' => null,
    'defaultPhone' => null,
    'statusUrl' => null,
    'successUrl' => null,
    'gateUrl' => null,
    'retryUrl' => null,
    'initialState' => 'details',
    'errorMessage' => null,
    'overlay' => true,
    'cancelUrl' => null,
    'adjustUrl' => null,
    'applyReward' => false,
    'simulateUrl' => null,
])

@php
    $statusUrl = $statusUrl ?? route('site.borrower.payments.status', $payment);
    $successUrl = $successUrl ?? app(\App\Services\CustomerPaymentService::class)->successRedirectUrl($payment);
    $gateUrl = $gateUrl ?? route('site.borrower.payments.gate', $payment);
    $retryUrl = $retryUrl ?? route('site.borrower.payments.retry', $payment);
    $formAction = $formAction ?? route('site.borrower.payments.pay', $payment);
    $payments = app(\App\Services\CustomerPaymentService::class);
    $surface = $payments->surfaceState($payment);
    $amountLabel = $surface['amount_label'];
    $displayPhone = $initialState === 'details'
        ? ($defaultPhone ?? $payment->customer?->phone)
        : ($payment->mobile_number ?: $defaultPhone ?: $payment->customer?->phone);
    $phoneMasked = $payments->maskPhoneForDisplay(is_string($displayPhone) ? $displayPhone : null)
        ?: $surface['phone_masked'];
    $rewardNet = null;
    if (is_array($walletReward) && ($walletReward['discount'] ?? 0) > 0) {
        $rewardNet = max(0, (float) $payment->amount - (float) $walletReward['discount']);
    }
    $copy = [
        'payAmount' => __('borrower.payment_waiting.pay_amount', ['amount' => $amountLabel]),
        'waitingTitle' => __('borrower.payment_waiting.title'),
        'waitingConfirmation' => __('borrower.payment_waiting.waiting_confirmation'),
        'successTitle' => __('borrower.payment_waiting.success_title'),
        'successPaid' => __('borrower.payment_waiting.success_paid', ['amount' => $amountLabel]),
        'failedTitle' => __('borrower.payment_waiting.failed_title'),
        'failedUsing' => __('borrower.payment_waiting.failed_using'),
        'timeoutTitle' => __('borrower.payment_waiting.timeout_title'),
        'timeoutBody' => __('borrower.payment_waiting.timeout_body'),
        'retry' => __('borrower.payment_waiting.retry'),
        'stayHint' => __('borrower.payment_waiting.stay_hint'),
        'slowHint' => __('borrower.payment_waiting.slow_hint'),
        'sentTo' => __('borrower.payment_waiting.sent_to'),
        'promoInvalid' => __('borrower.membership.promo_invalid'),
        'promoRequired' => __('borrower.apply.application_fee.promo_label'),
        'simulatorHeading' => __('borrower.payment_waiting.simulator_heading'),
        'simulatorSuccess' => __('borrower.payment_waiting.simulator_success'),
        'simulatorPending' => __('borrower.payment_waiting.simulator_pending'),
        'simulatorFailed' => __('borrower.payment_waiting.simulator_failed_btn'),
        'simulatorCancelled' => __('borrower.payment_waiting.simulator_cancelled'),
        'simulatorReversed' => __('borrower.payment_waiting.simulator_reversed'),
    ];
    $stagingPayments = app(\App\Services\Staging\StagingPaymentsService::class);
    $showSimulator = $stagingPayments->isSimulator();
    $resolvedSimulateUrl = $simulateUrl
        ?? ($showSimulator ? route('site.borrower.payments.simulate', $payment) : '');
@endphp

<div
    class="{{ $overlay ? 'kf-payment-surface' : 'max-w-xl mx-auto' }}"
    data-payment-surface
    data-retry-url="{{ $retryUrl }}"
    data-gate-url="{{ $gateUrl }}"
    data-status-url="{{ $statusUrl }}"
    x-data="pspPaymentFlow({
        initialState: @js($initialState),
        message: @js($errorMessage ?: ($initialState === 'waiting' ? $copy['waitingConfirmation'] : '')),
        paidTitle: @js($surface['title'] ?: $copy['successTitle']),
        amountLabel: @js($amountLabel),
        phoneMasked: @js($phoneMasked),
        successUrl: @js($successUrl),
        payUrl: @js($formAction),
        statusUrl: @js($statusUrl),
        retryUrl: @js($retryUrl),
        gateUrl: @js($gateUrl),
        copy: @js($copy),
        overlay: @js((bool) $overlay),
        applyReward: @js((bool) $applyReward),
        rewardDiscountLabel: @js(isset($walletReward['discount']) ? format_money((float) $walletReward['discount']) : ''),
        grossAmountLabel: @js($amountLabel),
        rewardNetLabel: @js($rewardNet !== null ? format_money($rewardNet) : $amountLabel),
        cancelUrl: @js($cancelUrl),
        adjustUrl: @js($adjustUrl ?? route('site.borrower.payments.adjust', $payment)),
        promoCode: @js($promoValue),
        promoValid: @js((bool) ($quote['promo_valid'] ?? false)),
        quoteLines: @js($quote['lines'] ?? []),
        cashDueLabel: @js(isset($quote['cash_due']) ? format_money((float) $quote['cash_due']) : $amountLabel),
        stackWithPromo: @js((bool) ($quote['stack_with_promo'] ?? false)),
        simulateUrl: @js($showSimulator ? $resolvedSimulateUrl : ''),
        simulatorEnabled: @js($showSimulator),
    })"
>
    <div class="kf-payment-surface-card">
        <div class="flex justify-center pt-3 pb-1 shrink-0 lg:hidden">
            <div class="w-10 h-1 rounded-full bg-gray-300"></div>
        </div>
        <div class="px-5 sm:px-6 pt-2 pb-1">
            <p x-show="simulatorEnabled" class="mb-2 inline-flex rounded-full bg-amber-100 text-amber-900 text-[10px] font-bold uppercase tracking-widest px-2.5 py-1">{{ __('borrower.payment_waiting.simulator_heading') }}</p>
            <h2 class="text-lg sm:text-xl font-extrabold tracking-tight text-gray-900" x-text="surfaceTitle()"></h2>
        </div>
        <div class="flex-1 overflow-y-auto overscroll-contain px-5 sm:px-6 pb-6">
            <div x-show="state === 'details'" class="pt-3">
                <x-site.payment-gate-ready
                    :payment="$payment"
                    :bank-accounts="$bankAccounts"
                    :mobile-details="$mobileDetails"
                    :show-promo="$showPromo"
                    :quote="$quote"
                    :promo-value="$promoValue"
                    :wallet-reward="$walletReward"
                    :form-action="$formAction"
                    :default-phone="$defaultPhone"
                    :cancel-url="$cancelUrl"
                />
            </div>

            <div x-show="state === 'waiting'" x-cloak class="pt-4 space-y-5 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-muted">
                    <svg class="h-6 w-6 animate-spin text-brand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-gray-600">{{ __('borrower.payment_waiting.sent_to') }}</p>
                    <p class="text-lg font-extrabold tabular-nums" x-text="phoneMasked || @js($phoneMasked ?: '—')"></p>
                </div>
                <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-left space-y-2">
                    <div class="flex justify-between gap-3 text-sm">
                        <span class="text-gray-500">{{ __('borrower.payment_waiting.amount') }}</span>
                        <span class="font-bold tabular-nums text-brand" x-text="amountLabel">{{ $amountLabel }}</span>
                    </div>
                </div>
                <p class="text-sm font-semibold text-gray-800">{{ __('borrower.payment_waiting.waiting_confirmation') }}</p>
                <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden" aria-hidden="true">
                    <div class="h-full w-1/3 rounded-full bg-brand kf-payment-progress"></div>
                </div>
                <p class="text-sm text-gray-700">{{ __('borrower.payment_waiting.stay_hint') }}</p>
                <p class="text-xs text-gray-500">{{ __('borrower.payment_waiting.slow_hint') }}</p>
                <button type="button" @click="changeNumber()" :disabled="busy"
                        class="rounded-xl bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-800 text-sm font-bold px-5 py-2.5 disabled:opacity-60">
                    {{ __('borrower.payment_waiting.change_phone') }}
                </button>
                <div x-show="simulatorEnabled" class="pt-2 space-y-2 text-left">
                    <p class="text-[10px] uppercase tracking-widest font-bold text-amber-800">{{ __('borrower.payment_waiting.simulator_heading') }}</p>
                    <div class="grid sm:grid-cols-2 gap-2">
                        <button type="button" @click="simulateOutcome('success')" :disabled="busy" class="rounded-xl bg-brand text-white text-sm font-bold px-4 py-2.5 disabled:opacity-60">{{ __('borrower.payment_waiting.simulator_success') }}</button>
                        <button type="button" @click="simulateOutcome('pending')" :disabled="busy" class="rounded-xl bg-white ring-1 ring-gray-200 text-sm font-bold px-4 py-2.5 disabled:opacity-60">{{ __('borrower.payment_waiting.simulator_pending') }}</button>
                        <button type="button" @click="simulateOutcome('failed')" :disabled="busy" class="rounded-xl bg-white ring-1 ring-gray-200 text-sm font-bold px-4 py-2.5 disabled:opacity-60">{{ __('borrower.payment_waiting.simulator_failed_btn') }}</button>
                        <button type="button" @click="simulateOutcome('cancelled')" :disabled="busy" class="rounded-xl bg-white ring-1 ring-gray-200 text-sm font-bold px-4 py-2.5 disabled:opacity-60">{{ __('borrower.payment_waiting.simulator_cancelled') }}</button>
                        <button type="button" @click="simulateOutcome('reversed')" :disabled="busy" class="rounded-xl bg-white ring-1 ring-gray-200 text-sm font-bold px-4 py-2.5 disabled:opacity-60 sm:col-span-2">{{ __('borrower.payment_waiting.simulator_reversed') }}</button>
                    </div>
                </div>
            </div>

            <div x-show="state === 'paid'" x-cloak class="pt-6 space-y-5 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 ring-1 ring-emerald-200">
                    <svg class="h-7 w-7 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="text-sm text-gray-600" x-text="@js($copy['successPaid'])"></p>
                <a :href="successUrl" class="inline-flex w-full justify-center rounded-xl bg-brand text-white text-sm font-bold px-5 py-3">
                    {{ __('borrower.celebration.cta_continue') }}
                </a>
            </div>

            <div x-show="state === 'failed'" x-cloak class="pt-6 space-y-5 text-center">
                <p class="text-sm text-gray-600">{{ __('borrower.payment_waiting.failed_using') }}</p>
                <p class="text-lg font-extrabold tabular-nums" x-text="phoneMasked || @js($phoneMasked ?: '—')"></p>
                <p class="text-sm text-rose-800" x-show="message" x-text="message"></p>
                <div class="flex flex-col gap-2">
                    <button type="button" @click="tryAgain()" :disabled="busy"
                            class="w-full rounded-xl bg-brand text-white text-sm font-bold px-5 py-3 disabled:opacity-60">
                        {{ __('borrower.payment_waiting.try_again') }}
                    </button>
                    <button type="button" @click="changeNumber()" :disabled="busy"
                            class="w-full rounded-xl bg-white ring-1 ring-gray-200 text-gray-800 text-sm font-bold px-5 py-3 disabled:opacity-60">
                        {{ __('borrower.payment_waiting.change_phone') }}
                    </button>
                </div>
            </div>

            <div x-show="state === 'timeout'" x-cloak class="pt-6 space-y-5 text-center">
                <p class="text-sm text-gray-600">{{ __('borrower.payment_waiting.timeout_body') }}</p>
                <div class="flex flex-col gap-2">
                    <button type="button" @click="checkAgain()" :disabled="busy"
                            class="w-full rounded-xl bg-brand text-white text-sm font-bold px-5 py-3 disabled:opacity-60">
                        {{ __('borrower.payment_waiting.check_again') }}
                    </button>
                    <button type="button" @click="tryAgain()" :disabled="busy"
                            class="w-full rounded-xl bg-white ring-1 ring-gray-200 text-gray-800 text-sm font-bold px-5 py-3 disabled:opacity-60">
                        {{ __('borrower.payment_waiting.try_again') }}
                    </button>
                    <button type="button" @click="changeNumber()" :disabled="busy"
                            class="w-full rounded-xl bg-white ring-1 ring-gray-200 text-gray-800 text-sm font-bold px-5 py-3 disabled:opacity-60">
                        {{ __('borrower.payment_waiting.change_phone') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
