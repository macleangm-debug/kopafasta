<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Offer Summary — {{ $agreement->reference }}</title>
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
@endphp

<div class="header">
    <table style="width:100%"><tr>
        <td>
            <h1>{{ brand('legal_name') }}</h1>
            <div class="muted">Microfinance Services · Tanzania</div>
        </td>
        <td style="text-align:right">
            <div class="pill">Offer Summary</div>
            <div class="muted" style="margin-top:4px;">Reference: <strong>{{ $agreement->reference }}</strong></div>
            <div class="muted">Date: {{ now()->format('d M Y') }}</div>
        </td>
    </tr></table>
</div>

<p>Dear <strong>{{ $snapshot['customer_name'] ?: 'Customer' }}</strong>,</p>
<p>
    We are pleased to offer you the following loan facility. This is an <strong>offer summary</strong> — not a legal agreement.
    Repayment dates will be set only after disbursement. The enforceable contract is issued after you accept this offer and pay post-approval fees.
</p>

<h2>Approved facility</h2>
<table class="kv">
    <tr><td class="label">Application number</td><td class="value">{{ $snapshot['application_number'] }}</td></tr>
    <tr><td class="label">Product</td><td class="value">{{ $snapshot['product_name'] }} ({{ $snapshot['product_code'] }})</td></tr>
    <tr><td class="label">Approved amount</td><td class="value">{{ format_money($snapshot['principal']) }}</td></tr>
    <tr><td class="label">Interest rate</td><td class="value">{{ format_number(($snapshot['displayed_monthly_rate'] ?? $snapshot['interest_rate'] ?? 0) * 100, 2) }}% per month (reducing balance)</td></tr>
    <tr><td class="label">Tenure</td><td class="value">{{ $snapshot['tenure_months'] }} months</td></tr>
    <tr><td class="label">Repayment frequency</td><td class="value">{{ ucfirst($snapshot['repayment_cadence'] ?? 'weekly') }}</td></tr>
    <tr><td class="label">Number of instalments</td><td class="value">{{ $snapshot['installment_count'] ?? count($snapshot['repayment_schedule'] ?? []) }}</td></tr>
    <tr><td class="label">{{ $snapshot['installment_label'] ?? 'Instalment amount' }}</td><td class="value">{{ format_money($snapshot['estimated_emi']) }}</td></tr>
    <tr><td class="label">Total repayable</td><td class="value">{{ format_money($snapshot['total_repayable'] ?? 0) }}</td></tr>
    <tr><td class="label">Offer expires</td><td class="value">{{ \Illuminate\Support\Carbon::parse($expiresAt)->format('d M Y') }} ({{ $validityDays }} days)</td></tr>
</table>

<div class="notice">
    <strong>Next steps:</strong> Accept or decline this offer in your borrower portal.
    Acceptance leads to post-approval fee payment, then contract review. No repayment dates appear until the loan is disbursed.
</div>

<div class="footer">
    Offer summary only · Not a legal agreement · {{ brand('legal_name') }} · {{ brand('support_email') }}
</div>

</body>
</html>
