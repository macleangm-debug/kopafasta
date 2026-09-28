@props([
    'payment',
    'continueUrl' => null,
    'continueLabel' => null,
    'actions' => true,
])

@php
    $payments = app(\App\Services\CustomerPaymentService::class);
    $receipt = $payments->receiptPayload($payment);
    $continueUrl = $continueUrl ?: $payments->successRedirectUrl($payment);
    $continueLabel = $continueLabel ?: $payments->continueLabel($payment);
    // Borrower-facing receipt: Kopafasta payment reference only — never PSP/provider_ref.
    $rows = array_values(array_filter([
        ['label' => __('borrower.payments_page.show.type'), 'value' => $receipt['type']],
        ['label' => __('borrower.payments_page.show.payment_reference'), 'value' => $receipt['reference']],
        ['label' => __('borrower.payments_page.show.date'), 'value' => $receipt['paid_at_label']],
        ['label' => __('borrower.payments_page.show.status'), 'value' => $receipt['status']],
        filled($receipt['member_name'] ?? null)
            ? ['label' => __('borrower.payments_page.show.member_name'), 'value' => $receipt['member_name']]
            : null,
        filled($receipt['member_number'] ?? null)
            ? ['label' => __('borrower.payments_page.show.member_number'), 'value' => $receipt['member_number']]
            : null,
        filled($receipt['application_number'] ?? null)
            ? ['label' => __('borrower.payments_page.show.application_number'), 'value' => $receipt['application_number']]
            : null,
        filled($receipt['loan_number'] ?? null)
            ? ['label' => __('borrower.payments_page.show.loan_number'), 'value' => $receipt['loan_number']]
            : null,
        filled($receipt['phone_masked'] ?? null)
            ? ['label' => __('borrower.payments_page.show.mobile_number'), 'value' => $receipt['phone_masked']]
            : null,
    ]));
    if (is_array($receipt['allocation'] ?? null)) {
        $rows[] = ['label' => __('borrower.payments_page.show.amount_paid'), 'value' => format_money($receipt['allocation']['paid'])];
        $rows[] = ['label' => __('borrower.payments_page.show.amount_allocated'), 'value' => format_money($receipt['allocation']['allocated'])];
        $rows[] = ['label' => __('borrower.payments_page.show.remaining_due'), 'value' => format_money($receipt['allocation']['remaining'])];
    }
    $footerParts = array_values(array_filter([
        $receipt['legal_name'] ?? null,
        $receipt['support_phone'] ?? null,
        $receipt['support_email'] ?? null,
        $receipt['website'] ?? null,
    ]));
@endphp

<div class="space-y-4">
    <div class="mx-auto w-full max-w-[22rem] sm:max-w-[24rem]">
    <div id="kf-payment-receipt"
         data-kf-receipt
         data-brand="{{ $receipt['brand'] }}"
         data-mark="{{ $receipt['mark_url'] }}"
         data-filename="{{ $receipt['filename'] }}"
         data-kicker="{{ $receipt['kicker'] }}"
         data-amount="{{ $receipt['amount'] }}"
         data-amount-label="{{ __('borrower.payments_page.show.amount') }}"
         data-keep="{{ $receipt['keep_line'] }}"
         data-footer="{{ implode(' · ', $footerParts) }}"
         data-rows="{{ json_encode($rows, JSON_UNESCAPED_UNICODE) }}"
         class="kf-receipt kf-receipt--paper bg-white px-5 py-6 space-y-5">
        <div class="flex items-start justify-between gap-3">
            <x-site.brand-mark size="md" variant="dark" />
            <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-gray-500 pt-1">{{ $receipt['kicker'] }}</p>
        </div>

        <div>
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.show.amount') }}</p>
            <p class="mt-1 text-3xl font-extrabold tabular-nums tracking-tight text-gray-900">{{ $receipt['amount'] }}</p>
        </div>

        {{-- Narrow receipt: single stacked column — mirrored by PDF + paintReceipt. --}}
        <dl class="grid grid-cols-1 gap-3.5">
            @foreach ($rows as $row)
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $row['label'] }}</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900 {{ str_contains((string) $row['label'], __('borrower.payments_page.show.payment_reference')) || str_contains((string) $row['label'], __('borrower.payments_page.show.member_number')) ? 'font-mono' : '' }}">{{ $row['value'] }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($footerParts !== [])
            <p class="text-xs text-gray-600 leading-relaxed">{{ implode(' · ', $footerParts) }}</p>
        @endif
        <p class="text-xs font-semibold text-gray-800">{{ $receipt['keep_line'] }}</p>
    </div>
    </div>

    @if ($actions)
        <div class="flex flex-col sm:flex-row gap-2">
            <button type="button"
                    data-kf-save-receipt="#kf-payment-receipt"
                    class="inline-flex justify-center rounded-xl bg-white ring-1 ring-gray-200 text-gray-900 text-sm font-bold px-5 py-3 hover:bg-gray-50">
                {{ __('borrower.payments_page.show.save_receipt') }}
            </button>
            <button type="button"
                    data-kf-share-receipt="#kf-payment-receipt"
                    class="inline-flex justify-center rounded-xl bg-white ring-1 ring-gray-200 text-gray-900 text-sm font-bold px-5 py-3 hover:bg-gray-50">
                {{ __('borrower.payments_page.show.share_receipt') }}
            </button>
            <a href="{{ route('site.borrower.payments.receipt', $payment) }}"
               class="inline-flex justify-center rounded-xl bg-white ring-1 ring-gray-200 text-gray-900 text-sm font-bold px-5 py-3 hover:bg-gray-50">
                {{ __('borrower.payments_page.show.pdf_receipt') }}
            </a>
            <a href="{{ $continueUrl }}"
               class="inline-flex justify-center rounded-xl bg-brand-gold text-brand text-sm font-bold px-5 py-3 hover:brightness-95">
                {{ $continueLabel }}
            </a>
        </div>
    @endif
</div>
