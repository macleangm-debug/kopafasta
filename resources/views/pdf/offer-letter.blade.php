<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>{{ __('borrower.offer_letter.pdf.title', ['reference' => $agreement->reference]) }}</title>
<style>
    @page { margin: 24mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.55; }
    h1 { font-size: 18px; margin: 0 0 4px; color: #b45309; }
    h2 { font-size: 13px; margin: 18px 0 6px; color: #374151; text-transform: uppercase; letter-spacing: 1px; }
    .muted { color: #6b7280; font-size: 10px; }
    .header { border-bottom: 2px solid #b45309; padding-bottom: 10px; margin-bottom: 14px; }
    table.kv { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 6px 8px; vertical-align: top; border-bottom: 1px solid #f3f4f6; }
    table.kv td.label { color: #6b7280; width: 38%; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    table.kv td.value { color: #111827; font-weight: 600; }
    .notice { margin-top: 14px; padding: 12px; background: #f0f9ff; border: 1px solid #bae6fd; font-size: 10.5px; }
    .footer { margin-top: 30px; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
</style>
</head>
<body>

@php
    $validityDays = (int) ($snapshot['offer_validity_days'] ?? 14);
    $expiresAt = $agreement->expires_at?->toDateString()
        ?? $snapshot['offer_expires_at']
        ?? now()->addDays($validityDays)->toDateString();
    $cadences = __('borrower.agreement.repayment_cadences');
    $cadenceKey = $snapshot['repayment_cadence'] ?? 'weekly';
@endphp

<div class="header">
    <table style="width:100%"><tr>
        <td>
            <h1>{{ brand('legal_name') }}</h1>
            <div class="muted">{{ __('borrower.offer_letter.pdf.tagline') }}</div>
        </td>
        <td style="text-align:right">
            <div class="pill">{{ __('borrower.offer_letter.pdf.pill') }}</div>
            <div class="muted" style="margin-top:4px;">{{ __('borrower.offer_letter.pdf.reference') }}: <strong>{{ $agreement->reference }}</strong></div>
            <div class="muted">{{ __('borrower.offer_letter.pdf.date') }}: {{ now()->format('d M Y') }}</div>
        </td>
    </tr></table>
</div>

<p>{{ __('borrower.offer_letter.pdf.greeting', ['name' => $snapshot['customer_name'] ?: __('borrower.offer_letter.pdf.customer_fallback')]) }}</p>
<p>{{ __('borrower.offer_letter.pdf.intro') }}</p>

<h2>{{ __('borrower.offer_letter.pdf.facility_heading') }}</h2>
<table class="kv">
    <tr><td class="label">{{ __('borrower.offer_letter.pdf.application_number') }}</td><td class="value">{{ $snapshot['application_number'] }}</td></tr>
    <tr><td class="label">{{ __('borrower.offer_letter.pdf.product') }}</td><td class="value">{{ $snapshot['product_name'] }} ({{ $snapshot['product_code'] }})</td></tr>
    <tr><td class="label">{{ __('borrower.offer_letter.pdf.approved_amount') }}</td><td class="value">{{ format_money($snapshot['principal']) }}</td></tr>
    <tr><td class="label">{{ __('borrower.offer_letter.pdf.interest_rate') }}</td><td class="value">{{ __('borrower.offer_letter.pdf.interest_rate_value', ['rate' => format_number(($snapshot['displayed_monthly_rate'] ?? $snapshot['interest_rate'] ?? 0) * 100, 2)]) }}</td></tr>
    <tr><td class="label">{{ __('borrower.offer_letter.pdf.tenure') }}</td><td class="value">{{ __('borrower.offer_letter.pdf.tenure_months', ['months' => $snapshot['tenure_months']]) }}</td></tr>
    <tr><td class="label">{{ __('borrower.offer_letter.pdf.repayment_frequency') }}</td><td class="value">{{ $cadences[$cadenceKey] ?? ucfirst($cadenceKey) }}</td></tr>
    <tr><td class="label">{{ __('borrower.offer_letter.pdf.installment_count') }}</td><td class="value">{{ $snapshot['installment_count'] ?? count($snapshot['repayment_schedule'] ?? []) }}</td></tr>
    <tr><td class="label">{{ $snapshot['installment_label'] ?? __('borrower.offer_letter.pdf.installment_amount') }}</td><td class="value">{{ format_money($snapshot['estimated_emi']) }}</td></tr>
    <tr><td class="label">{{ __('borrower.offer_letter.pdf.total_repayable') }}</td><td class="value">{{ format_money($snapshot['total_repayable'] ?? 0) }}</td></tr>
    <tr><td class="label">{{ __('borrower.offer_letter.pdf.offer_expires') }}</td><td class="value">{{ __('borrower.offer_letter.pdf.offer_expires_value', ['date' => \Illuminate\Support\Carbon::parse($expiresAt)->format('d M Y'), 'days' => $validityDays]) }}</td></tr>
</table>

<div class="notice">
    <strong>{{ __('borrower.offer_letter.pdf.next_steps_heading') }}</strong> {{ __('borrower.offer_letter.pdf.next_steps_body') }}
</div>

<div class="footer">
    {{ __('borrower.offer_letter.pdf.footer', ['company' => brand('legal_name'), 'email' => brand('support_email')]) }}
</div>

</body>
</html>
