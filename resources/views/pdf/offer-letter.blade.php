<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Loan Offer Letter — {{ $agreement->reference }}</title>
<style>
    @page { margin: 24mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.55; }
    h1 { font-size: 18px; margin: 0 0 4px; color: #b45309; }
    h2 { font-size: 13px; margin: 18px 0 6px; color: #374151; text-transform: uppercase; letter-spacing: 1px; }
    .muted { color: #6b7280; font-size: 10px; }
    .header { border-bottom: 2px solid #b45309; padding-bottom: 10px; margin-bottom: 14px; }
    table.kv { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 4px 6px; vertical-align: top; }
    table.kv td.label { color: #6b7280; width: 38%; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    table.kv td.value { color: #111827; font-weight: 600; }
    table.schedule { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 10px; }
    table.schedule th, table.schedule td { border: 1px solid #e5e7eb; padding: 4px 6px; text-align: left; }
    table.schedule th { background: #f9fafb; text-transform: uppercase; font-size: 9px; }
    .terms { margin-top: 10px; font-size: 10.5px; }
    .terms li { margin-bottom: 5px; }
    .signbox { margin-top: 24px; padding: 14px; border: 1px dashed #b45309; border-radius: 6px; background: #fffbeb; }
    .sign-col { width: 32%; display: inline-block; vertical-align: top; margin-right: 1%; }
    .footer { margin-top: 30px; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
</style>
</head>
<body>

<div class="header">
    <table style="width:100%"><tr>
        <td>
            <h1>{{ brand('legal_name') }}</h1>
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
    <tr><td class="label">Principal</td><td class="value">TZS {{ number_format($snapshot['principal']) }}</td></tr>
    <tr><td class="label">Tenure</td><td class="value">{{ $snapshot['tenure_months'] }} months ({{ $snapshot['installment_count'] }} {{ $snapshot['repayment_cadence'] === 'monthly' ? 'monthly' : 'weekly' }} instalments)</td></tr>
    <tr><td class="label">{{ $snapshot['installment_label'] ?? 'Instalment' }}</td><td class="value">TZS {{ number_format($snapshot['estimated_emi']) }}</td></tr>
    <tr><td class="label">Interest (total)</td><td class="value">TZS {{ number_format($snapshot['total_interest'] ?? 0) }}</td></tr>
    <tr><td class="label">Fees</td><td class="value">TZS {{ number_format($snapshot['total_fees'] ?? 0) }}</td></tr>
    <tr><td class="label">Interest rate (displayed)</td><td class="value">{{ number_format(($snapshot['displayed_monthly_rate'] ?? $snapshot['interest_rate'] ?? 0) * 100, 2) }}% per month (reducing balance)</td></tr>
    @if (! empty($snapshot['rate_breakdown']))
    <tr><td class="label">BOT regulated interest</td><td class="value">{{ number_format(($snapshot['rate_breakdown']['bot_regulated_rate'] ?? 0) * 100, 2) }}% / month</td></tr>
    <tr><td class="label">Internal fees</td><td class="value">{{ number_format(($snapshot['rate_breakdown']['internal_fee_rate'] ?? 0) * 100, 2) }}% / month (processing, service, administration)</td></tr>
    @endif
    <tr><td class="label">Purpose</td><td class="value">{{ $snapshot['purpose'] ?: '—' }}</td></tr>
</table>

<h2>Repayment schedule</h2>
<table class="schedule">
    <thead>
        <tr>
            <th>Period</th>
            <th>Due date</th>
            <th>Principal</th>
            <th>Interest</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach (($snapshot['repayment_schedule'] ?? []) as $row)
            <tr>
                <td>{{ $row['label'] ?? ('#'.$row['installment_no']) }}</td>
                <td>{{ \Illuminate\Support\Carbon::parse($row['due_date'])->format('d M Y') }}</td>
                <td>TZS {{ number_format($row['principal_due']) }}</td>
                <td>TZS {{ number_format($row['interest_due']) }}</td>
                <td>TZS {{ number_format($row['total_due']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if (!empty($snapshot['is_asset_loan']))
<h2>Asset ownership</h2>
<p><strong>Financed asset:</strong> {{ $snapshot['asset_title'] ?: 'As described in the loan application' }}</p>
<p>{{ $snapshot['asset_ownership_note'] }}</p>
@endif

<h2>Terms &amp; conditions</h2>
<ol class="terms">
    <li><strong>Default process:</strong> Failure to pay any instalment by the due date constitutes default. Penalty interest and collection fees may apply per the schedule of charges.</li>
    <li><strong>Collection process:</strong> The lender may contact you by phone, SMS, email, or in person to recover overdue amounts.</li>
    <li><strong>Recovery process:</strong> Persistent default may result in legal recovery action, reporting to credit reference bureaus, and enforcement of security interests.</li>
    <li><strong>Guarantor liability:</strong> Where a guarantor has signed, they become jointly liable for repayment of the outstanding balance.</li>
    <li><strong>Repossession rights:</strong> For asset-backed facilities, the lender may repossess financed assets upon default subject to applicable law.</li>
    <li><strong>Auction rights:</strong> Repossessed assets may be sold by public auction or private treaty to recover outstanding debt.</li>
    <li><strong>Debt collection rights:</strong> The lender may engage licensed debt collection agents to recover overdue amounts.</li>
    <li>This offer is valid for <strong>14 days</strong> from the date of issue.</li>
</ol>

<h2>Signatures</h2>
<div class="signbox">
    <div class="sign-col">
        <strong>Borrower</strong>
        @if (!empty($snapshot['borrower_signature']))
            <div style="margin-top:6px"><img src="{{ $snapshot['borrower_signature']->signature_data }}" alt="Borrower signature" style="max-height:50px"></div>
            <div class="muted">{{ $snapshot['borrower_signature']->signer_name }}</div>
            <div class="muted">{{ optional($snapshot['borrower_signature']->signed_at)->format('d M Y H:i') }}</div>
        @else
            <div class="muted" style="margin-top:8px">Pending acceptance</div>
        @endif
    </div>
    <div class="sign-col">
        <strong>Guarantor</strong>
        @if (!empty($snapshot['guarantor_signature']))
            <div style="margin-top:6px"><img src="{{ $snapshot['guarantor_signature']->signature_data }}" alt="Guarantor signature" style="max-height:50px"></div>
            <div class="muted">{{ $snapshot['guarantor_signature']->signer_name }}</div>
        @else
            <div class="muted" style="margin-top:8px">If applicable</div>
        @endif
    </div>
    <div class="sign-col">
        <strong>Company</strong>
        <div class="muted" style="margin-top:8px">{{ $snapshot['company_signatory'] ?? brand('legal_name') }}</div>
        @if ($agreement->isSigned())
            <div class="muted">Authorised: {{ $agreement->signed_at->format('d M Y') }}</div>
        @endif
    </div>
</div>

<div class="footer">
    System-generated document · {{ brand('legal_name') }} · {{ brand('support_email') }}
</div>

</body>
</html>
