<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Loan Offer Letter — {{ $agreement->reference }}</title>
<style>
    @page { margin: 28mm 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.55; }
    h1 { font-size: 18px; margin: 0 0 4px; color: #b45309; }
    h2 { font-size: 13px; margin: 18px 0 6px; color: #374151; text-transform: uppercase; letter-spacing: 1px; }
    .muted { color: #6b7280; font-size: 10px; }
    .header { border-bottom: 2px solid #b45309; padding-bottom: 10px; margin-bottom: 14px; }
    table.kv { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 4px 6px; vertical-align: top; }
    table.kv td.label { color: #6b7280; width: 38%; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    table.kv td.value { color: #111827; font-weight: 600; }
    .terms { margin-top: 10px; font-size: 10.5px; }
    .terms li { margin-bottom: 5px; }
    .signbox { margin-top: 30px; padding: 14px; border: 1px dashed #b45309; border-radius: 6px; background: #fffbeb; }
    .footer { margin-top: 30px; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
</style>
</head>
<body>

<div class="header">
    <table style="width:100%"><tr>
        <td>
            <h1>Kopa Fasta</h1>
            <div class="muted">Microfinance Services · Tanzania</div>
        </td>
        <td style="text-align:right">
            <div class="pill">Offer Letter</div>
            <div class="muted" style="margin-top:4px;">Reference: <strong>{{ $agreement->reference }}</strong></div>
            <div class="muted">Date: {{ now()->format('d M Y') }}</div>
        </td>
    </tr></table>
</div>

<p>Dear <strong>{{ $snapshot['customer_name'] ?: 'Customer' }}</strong>,</p>

<p>We are pleased to offer you the following loan facility, subject to your acceptance of the terms and conditions outlined in this letter.</p>

<h2>Loan summary</h2>
<table class="kv">
    <tr><td class="label">Application number</td><td class="value">{{ $snapshot['application_number'] }}</td></tr>
    <tr><td class="label">Product</td><td class="value">{{ $snapshot['product_name'] }} ({{ $snapshot['product_code'] }})</td></tr>
    <tr><td class="label">Approved principal</td><td class="value">TZS {{ number_format($snapshot['principal']) }}</td></tr>
    <tr><td class="label">Interest rate</td><td class="value">{{ number_format($snapshot['interest_rate'] * 100, 2) }}% per month (reducing balance)</td></tr>
    <tr><td class="label">Tenure</td><td class="value">{{ $snapshot['tenure_months'] }} months</td></tr>
    <tr><td class="label">Estimated monthly instalment</td><td class="value">TZS {{ number_format($snapshot['estimated_emi']) }}</td></tr>
    <tr><td class="label">Purpose</td><td class="value">{{ $snapshot['purpose'] ?: '—' }}</td></tr>
</table>

<h2>Borrower</h2>
<table class="kv">
    <tr><td class="label">Full name</td><td class="value">{{ $snapshot['customer_name'] }}</td></tr>
    <tr><td class="label">National ID</td><td class="value">{{ $snapshot['customer_id'] ?: '—' }}</td></tr>
    <tr><td class="label">Phone</td><td class="value">{{ $snapshot['customer_phone'] ?: '—' }}</td></tr>
</table>

<h2>Terms &amp; conditions</h2>
<ol class="terms">
    <li>This offer is valid for <strong>14 days</strong> from the date of issue. Acceptance is required within this period.</li>
    <li>Disbursement is subject to completion of KYC, satisfactory verification of all submitted documents and any collateral perfection that may apply.</li>
    <li>Fees and charges, where applicable, will be deducted from the principal at disbursement and itemised on your loan account statement.</li>
    <li>Repayments fall due monthly per the schedule generated at disbursement. Late payments attract penalty interest as per the prevailing schedule of charges.</li>
    <li>The lender reserves the right to recall the loan if any information provided is found to be materially false or in the event of default.</li>
    <li>By signing this offer letter electronically (via OTP), you confirm that you have read and accepted these terms.</li>
</ol>

<div class="signbox">
    <strong>Acceptance</strong>
    @if (!empty($snapshot['borrower_signature']))
        <div style="margin-top:8px">
            <div class="muted">Application signature — {{ $snapshot['borrower_signature']->signer_name }}</div>
            <div style="margin-top:6px"><img src="{{ $snapshot['borrower_signature']->signature_data }}" alt="Borrower signature" style="max-height:60px"></div>
            <div class="muted" style="margin-top:6px">Signed at: <strong>{{ $snapshot['borrower_signature']->signed_at->format('d M Y H:i') }}</strong></div>
        </div>
    @endif
    @if ($agreement->isSigned())
        <div style="margin-top:8px">
            <span class="pill" style="background:#d1fae5;color:#065f46">Signed</span>
            <div class="muted" style="margin-top:6px">
                Signed at: <strong>{{ $agreement->signed_at->format('d M Y H:i') }}</strong><br>
                Method: <strong>{{ strtoupper($agreement->signature_method ?? 'otp') }}</strong><br>
                Source IP: <strong>{{ $agreement->signed_ip ?: '—' }}</strong>
            </div>
        </div>
    @else
        <div class="muted" style="margin-top:6px">Pending borrower acceptance via OTP confirmation.</div>
    @endif
</div>

<div class="footer">
    This is a system-generated document. For questions, contact Kopa Fasta customer care.
</div>

</body>
</html>
