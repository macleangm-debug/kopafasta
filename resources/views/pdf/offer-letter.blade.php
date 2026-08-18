<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>{{ pdf_text(__('borrower.offer_letter.pdf.title', ['reference' => $agreement->reference])) }}</title>
@include('pdf.loan-agreement._styles')
</head>
<body>
@php require resource_path('views/pdf/loan-agreement/locale.php'); @endphp
@php
    $validityDays = (int) ($snapshot['offer_validity_days'] ?? 14);
    $expiresAt = $agreement->expires_at?->toDateString()
        ?? $snapshot['offer_expires_at']
        ?? now()->addDays($validityDays)->toDateString();
    $cadences = __('borrower.agreement.repayment_cadences');
    $cadenceKey = $snapshot['repayment_cadence'] ?? 'weekly';
    $customerName = $snapshot['customer_name'] ?: __('borrower.offer_letter.pdf.customer_fallback');
@endphp

@include('pdf._brand_band', [
    'bandTitle' => $snapshot['company_legal_name'] ?? brand('legal_name'),
    'bandTag' => pdf_text(__('borrower.offer_letter.pdf.tagline')),
    'bandMeta' => '<div class="pill">'.e(pdf_text(__('borrower.offer_letter.pdf.pill'))).'</div>'
        .'<div class="tag" style="margin-top:8px">'.e(pdf_text(__('borrower.offer_letter.pdf.reference'))).': <strong>'.e($agreement->reference).'</strong></div>'
        .'<div class="tag">'.e(pdf_text(__('borrower.offer_letter.pdf.date'))).': '.e(now()->format('d M Y')).'</div>',
])

<div class="wrap">
    <p>{{ pdf_text(__('borrower.offer_letter.pdf.greeting', ['name' => $customerName])) }}</p>
    <p>{{ pdf_text(__('borrower.offer_letter.pdf.intro')) }}</p>

    @include('pdf.loan-agreement._parties')

    <h2>{{ pdf_text(__('borrower.offer_letter.pdf.facility_heading')) }}</h2>
    <table class="kv">
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.application_number')) }}</td><td class="value">{{ $snapshot['application_number'] }}</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.product')) }}</td><td class="value">{{ pdf_text($snapshot['product_name']) }} ({{ $snapshot['product_code'] }})</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.approved_amount')) }}</td><td class="value">{{ format_money($snapshot['principal']) }}</td></tr>
        <tr><td class="label">{{ pdf_text(! empty($snapshot['hides_interest']) ? __('borrower.offer_letter.pdf.charge_rate') : __('borrower.offer_letter.pdf.interest_rate')) }}</td><td class="value">{{ pdf_text(! empty($snapshot['hides_interest']) ? __('borrower.offer_letter.pdf.charge_rate_value', ['rate' => format_number(($snapshot['displayed_monthly_rate'] ?? $snapshot['interest_rate'] ?? 0) * 100, 2)]) : __('borrower.offer_letter.pdf.interest_rate_value', ['rate' => format_number(($snapshot['displayed_monthly_rate'] ?? $snapshot['interest_rate'] ?? 0) * 100, 2)])) }}</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.tenure')) }}</td><td class="value">{{ pdf_text(__('borrower.offer_letter.pdf.tenure_months', ['months' => $snapshot['tenure_months']])) }}</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.repayment_frequency')) }}</td><td class="value">{{ pdf_text($cadences[$cadenceKey] ?? ucfirst($cadenceKey)) }}</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.installment_count')) }}</td><td class="value">{{ $snapshot['installment_count'] ?? count($snapshot['repayment_schedule'] ?? []) }}</td></tr>
        <tr><td class="label">{{ pdf_text($snapshot['installment_label'] ?? __('borrower.offer_letter.pdf.installment_amount')) }}</td><td class="value">{{ format_money($snapshot['estimated_emi']) }}</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.total_repayable')) }}</td><td class="value">{{ format_money($snapshot['total_repayable'] ?? 0) }}</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.offer_expires')) }}</td><td class="value">{{ pdf_text(__('borrower.offer_letter.pdf.offer_expires_value', ['date' => \Illuminate\Support\Carbon::parse($expiresAt)->format('d M Y'), 'days' => $validityDays])) }}</td></tr>
    </table>

    <div class="notice">
        <strong>{{ $t('Acceptance of commercial terms', 'Kukubali masharti ya kibiashara') }}</strong>
        @if ($isSw)
            <p>Kwa kusaini Barua hii ya Ofa, Mkopaji na, pale inapohusika, Mdhamini na Wanachama wa Kikundi wanathibitisha wamesoma na kukubali kiasi, riba, ratiba, ada, masharti ya default na wajibu, kulingana na kutia saini Mkataba wa Mkopo na Masharti ya Huduma ya Mkopo.</p>
        @else
            <p>By signing this Offer Letter, the Borrower and, where applicable, the Guarantor and Group Members acknowledge that they have reviewed and accepted the principal amount, interest, repayment schedule, applicable charges, default provisions and the obligations described herein, subject to execution of the Loan Agreement and Facility Terms.</p>
        @endif
        <p><strong>{{ pdf_text(__('borrower.offer_letter.pdf.next_steps_heading')) }}</strong> {{ pdf_text(__('borrower.offer_letter.pdf.next_steps_body')) }}</p>
    </div>

    @include('pdf.loan-agreement._signatories')

    <div class="footer">
        {{ pdf_text(__('borrower.offer_letter.pdf.footer', ['company' => $snapshot['company_legal_name'] ?? brand('legal_name'), 'email' => $snapshot['complaints_email'] ?? brand('support_email')])) }}
    </div>
</div>
</body>
</html>
