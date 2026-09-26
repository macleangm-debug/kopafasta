@props([
    'payment',
    'continueUrl' => null,
    'continueLabel' => null,
])

@php
    $payments = app(\App\Services\CustomerPaymentService::class);
    $continueUrl = $continueUrl ?: $payments->successRedirectUrl($payment);
    $continueLabel = $continueLabel ?: $payments->continueLabel($payment);
    $paidAt = $payment->paid_at ?? $payment->verified_at ?? $payment->created_at;
    $markUrl = asset(ltrim((string) (brand('logo_mark_url') ?: '/images/brand/kopafasta-mark.png'), '/'));
    $filename = 'kopafasta-receipt-'.preg_replace('/[^A-Za-z0-9\-]/', '', (string) $payment->reference).'.png';
@endphp

<div class="space-y-4">
    <div id="kf-payment-receipt"
         data-kf-receipt
         data-brand="{{ brand_name() }}"
         data-mark="{{ $markUrl }}"
         data-filename="{{ $filename }}"
         data-kicker="{{ __('borrower.payments_page.show.receipt') }}"
         data-amount="{{ format_money((float) $payment->amount) }}"
         data-type-label="{{ __('borrower.payments_page.show.type') }}"
         data-type="{{ $payment->typeLabel() }}"
         data-reference-label="{{ __('borrower.payments_page.show.payment_reference') }}"
         data-reference="{{ $payment->reference }}"
         data-date-label="{{ __('borrower.payments_page.show.date') }}"
         data-date="{{ $paidAt?->format('d M Y') }}"
         data-status-label="{{ __('borrower.payments_page.show.status') }}"
         data-status="{{ $payment->statusLabel() }}"
         data-phone-label="{{ __('borrower.payments_page.show.mobile_number') }}"
         data-phone="{{ $payment->mobile_number ?: '' }}"
         class="kf-receipt rounded-2xl bg-white ring-1 ring-gray-200 px-5 py-6 sm:px-7 space-y-5">
        <div class="flex items-start justify-between gap-3">
            <x-site.brand-mark size="md" variant="dark" />
            <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-gray-500 pt-1">{{ __('borrower.payments_page.show.receipt') }}</p>
        </div>

        <div>
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.show.amount') }}</p>
            <p class="mt-1 text-3xl font-extrabold tabular-nums tracking-tight text-gray-900">{{ format_money((float) $payment->amount) }}</p>
        </div>

        <dl class="grid sm:grid-cols-2 gap-4">
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.show.type') }}</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $payment->typeLabel() }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.show.payment_reference') }}</dt>
                <dd class="mt-1 font-mono text-sm font-semibold text-gray-900">{{ $payment->reference }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.show.date') }}</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $paidAt?->format('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.show.status') }}</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $payment->statusLabel() }}</dd>
            </div>
            @if ($payment->mobile_number)
                <div class="sm:col-span-2">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.show.mobile_number') }}</dt>
                    <dd class="mt-1 font-mono text-sm font-semibold text-gray-900">{{ $payment->mobile_number }}</dd>
                </div>
            @endif
        </dl>
    </div>

    <div class="flex flex-col sm:flex-row gap-2">
        <button type="button"
                data-kf-save-receipt="#kf-payment-receipt"
                class="inline-flex justify-center rounded-xl bg-white ring-1 ring-gray-200 text-gray-900 text-sm font-bold px-5 py-3 hover:bg-gray-50">
            {{ __('borrower.payments_page.show.save_receipt') }}
        </button>
        <a href="{{ $continueUrl }}"
           class="inline-flex justify-center rounded-xl bg-brand-gold text-brand text-sm font-bold px-5 py-3 hover:brightness-95">
            {{ $continueLabel }}
        </a>
    </div>
</div>
