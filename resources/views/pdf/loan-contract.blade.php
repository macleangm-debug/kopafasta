<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Loan Contract — {{ $agreement->reference }}</title>
<style>
    @page { margin: 22mm 14mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2937; line-height: 1.5; }
    h1 { font-size: 17px; margin: 0 0 4px; color: #b45309; }
    h2 { font-size: 12px; margin: 16px 0 6px; color: #374151; text-transform: uppercase; letter-spacing: 0.8px; }
    .muted { color: #6b7280; font-size: 9.5px; }
    .header { border-bottom: 2px solid #b45309; padding-bottom: 8px; margin-bottom: 12px; }
    table.kv { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.kv td { padding: 3px 5px; vertical-align: top; }
    table.kv td.label { color: #6b7280; width: 34%; font-size: 9px; text-transform: uppercase; }
    table.kv td.value { color: #111827; font-weight: 600; }
    table.schedule { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 9px; }
    table.schedule th, table.schedule td { border: 1px solid #e5e7eb; padding: 3px 5px; }
    .terms li { margin-bottom: 4px; }
    .charges { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 6px; }
    .charges td { padding: 4px 6px; border: 1px solid #e5e7eb; }
    .charges td:first-child { background: #f9fafb; width: 38%; font-weight: 600; }
    .signbox { margin-top: 20px; padding: 12px; border: 1px dashed #b45309; background: #fffbeb; }
    .sign-row { width: 100%; }
    .sign-col { width: 24%; display: inline-block; vertical-align: bottom; padding-right: 1%; }
    .sig-img { max-height: 72px; max-width: 180px; }
    .stamp-img { max-height: 72px; max-width: 72px; margin-top: 4px; }
</style>
</head>
<body>

@php
    $clauses = $snapshot['legal_clauses'] ?? [];
    $sections = $snapshot['contract_sections'] ?? [];
    $show = fn (string $key): bool => (bool) ($sections[$key] ?? true);
@endphp

<div class="header">
    <h1>{{ brand('legal_name') }} — Loan Contract</h1>
    <div class="muted">Reference: {{ $agreement->reference }} · Application: {{ $snapshot['application_number'] }}</div>
</div>

@if ($show('definitions'))
<p>
    This loan contract ("Agreement") is entered into on {{ now()->format('d M Y') }} between
    <strong>{{ brand('legal_name') }}</strong> ("Lender") and <strong>{{ $snapshot['customer_name'] }}</strong> ("Borrower").
</p>

<h2>Borrower</h2>
<table class="kv">
    <tr><td class="label">Name</td><td class="value">{{ $snapshot['customer_name'] }}</td></tr>
    <tr><td class="label">NIDA number</td><td class="value">{{ $snapshot['customer_id'] ?? '—' }}</td></tr>
    <tr><td class="label">Address</td><td class="value">{{ $snapshot['customer_address'] ?? '—' }}</td></tr>
    <tr><td class="label">Activity</td><td class="value">{{ $snapshot['customer_activity'] ?? '—' }}</td></tr>
    <tr><td class="label">Income</td><td class="value">{{ $snapshot['customer_income'] ?? '—' }}</td></tr>
    <tr><td class="label">Phone</td><td class="value">{{ $snapshot['customer_phone'] ?? '—' }}</td></tr>
</table>

@if ($show('guarantor_obligations') && ! empty($snapshot['guarantor_name']))
<h2>Guarantor</h2>
<table class="kv">
    <tr><td class="label">Name</td><td class="value">{{ $snapshot['guarantor_name'] }}</td></tr>
    <tr><td class="label">NIDA number</td><td class="value">{{ $snapshot['guarantor_nida'] ?? '—' }}</td></tr>
    <tr><td class="label">Address</td><td class="value">{{ $snapshot['guarantor_address'] ?? '—' }}</td></tr>
    <tr><td class="label">Phone</td><td class="value">{{ $snapshot['guarantor_phone'] ?? '—' }}</td></tr>
    <tr><td class="label">Relationship</td><td class="value">{{ $snapshot['guarantor_relationship'] ?? '—' }}</td></tr>
</table>
@endif
@endif

@if ($show('loan_terms'))
<h2>Loan facility</h2>
<table class="kv">
    <tr><td class="label">Reference</td><td class="value">{{ $agreement->reference }}</td></tr>
    <tr><td class="label">Amount</td><td class="value">{{ format_money($snapshot['principal']) }}</td></tr>
    <tr><td class="label">Interest</td><td class="value">{{ format_number(($snapshot['displayed_monthly_rate'] ?? 0) * 100, 2) }}% per month</td></tr>
    <tr><td class="label">Tenure</td><td class="value">{{ $snapshot['tenure_months'] }} months</td></tr>
    <tr><td class="label">{{ $snapshot['installment_label'] ?? 'Instalment' }}</td><td class="value">{{ format_money($snapshot['estimated_emi']) }}</td></tr>
    <tr><td class="label">Total repayable</td><td class="value">{{ format_money($snapshot['total_repayable'] ?? 0) }}</td></tr>
</table>
@endif

@if ($show('repayment_obligations'))
<h2>Repayment terms</h2>
<p>
    Repayments shall commence
    <strong>{{ (int) ($snapshot['repayment_commencement_days'] ?? 7) }} days</strong>
    after disbursement of the loan amount, then continue at
    {{ ucfirst($snapshot['repayment_cadence'] ?? 'weekly') }} intervals for
    {{ $snapshot['installment_count'] ?? count($snapshot['repayment_schedule'] ?? []) }} instalments of
    approximately <strong>{{ format_money($snapshot['estimated_emi'] ?? 0) }}</strong> each.
</p>
<p class="muted">
    A dated repayment schedule will be issued as an annex after disbursement.
</p>
@endif

@if (!empty($snapshot['is_asset_loan']))
<h2>Asset ownership</h2>
<table class="kv">
    <tr><td class="label">Financed asset</td><td class="value">{{ $snapshot['asset_title'] ?: 'As described in the loan application' }}</td></tr>
    @if (!empty($snapshot['asset_supplier']))
    <tr><td class="label">Supplier</td><td class="value">{{ $snapshot['asset_supplier'] }}</td></tr>
    @endif
    @if (!empty($snapshot['asset_serial_number']))
    <tr><td class="label">Serial / registration</td><td class="value">{{ $snapshot['asset_serial_number'] }}</td></tr>
    @endif
    @if (!empty($snapshot['asset_chassis_number']))
    <tr><td class="label">Chassis number</td><td class="value">{{ $snapshot['asset_chassis_number'] }}</td></tr>
    @endif
    @if (!empty($snapshot['asset_engine_number']))
    <tr><td class="label">Engine number</td><td class="value">{{ $snapshot['asset_engine_number'] }}</td></tr>
    @endif
    @if (!empty($snapshot['asset_insurance_policy']))
    <tr><td class="label">Insurance policy</td><td class="value">{{ $snapshot['asset_insurance_policy'] }}</td></tr>
    @endif
</table>
@if (!empty($snapshot['collateral_description']))
<p><strong>Description:</strong> {{ $snapshot['collateral_description'] }}</p>
@endif
@if (!empty($snapshot['collateral_market_value']))
<p><strong>Market value:</strong> {{ format_money($snapshot['collateral_market_value']) }}</p>
@endif
@if (!empty($snapshot['collateral_forced_sale_value']))
<p><strong>Forced sale value:</strong> {{ format_money($snapshot['collateral_forced_sale_value']) }}</p>
@endif
@if (!empty($snapshot['collateral_gps_required']))
<p><strong>GPS tracking:</strong> Required for this asset for the loan tenure.</p>
@endif
<p>{{ $snapshot['asset_ownership_note'] }}</p>
@endif

@if ($show('penalty_clauses'))
<h2>Charges &amp; penalties</h2>
<table class="charges">
    <tr><td>Penalty rate</td><td>{{ $clauses['penalty_rate_label'] ?? 'As per schedule of charges' }}</td></tr>
    <tr><td>Grace period</td><td>{{ (int) ($clauses['grace_days'] ?? 7) }} days after missed instalment</td></tr>
    <tr><td>Penalty cap</td><td>{{ format_number($clauses['penalty_cap_percent'] ?? 30, 0) }}% of amount owed (BOT limit)</td></tr>
    <tr><td>Late fee</td><td>{{ $clauses['late_fee_label'] ?? format_money(2000) }}</td></tr>
    <tr><td>Collection charge</td><td>{{ $clauses['collection_charge'] ?? 'Actual cost incurred' }}</td></tr>
    <tr><td>Legal recovery</td><td>{{ $clauses['legal_recovery'] ?? 'Borrower responsible' }}</td></tr>
</table>
@endif

@if ($show('default_events') || $show('recovery_clauses') || $show('legal_costs') || $show('jurisdiction') || $show('data_privacy'))
<h2>Legal clauses</h2>
<ol class="terms">
    @if ($show('default_events'))
        <li><strong>Default:</strong> {{ $clauses['default_clause'] ?? '' }}</li>
        <li><strong>Collection:</strong> {{ $clauses['collection_clause'] ?? '' }}</li>
    @endif
    @if ($show('recovery_clauses'))
        <li><strong>Recovery:</strong> {{ $clauses['recovery_clause'] ?? '' }}</li>
    @endif
    @if ($show('penalty_clauses'))
        <li><strong>Penalty charges:</strong> {{ $clauses['penalty_clause'] ?? '' }}</li>
    @endif
    @if ($show('legal_costs'))
        <li><strong>Legal costs:</strong> {{ $clauses['legal_cost_clause'] ?? '' }}</li>
    @endif
    @if (! empty($snapshot['is_asset_loan']))
        <li><strong>Asset recovery:</strong> {{ $clauses['asset_recovery_clause'] ?? '' }}</li>
    @endif
    @if ($show('guarantor_obligations') && ! empty($snapshot['guarantor_name']))
        <li><strong>Guarantor liability:</strong> {{ $clauses['guarantor_clause'] ?? '' }}</li>
    @endif
    @if ($show('jurisdiction'))
        <li><strong>Jurisdiction:</strong> This Agreement is governed by the laws of {{ $clauses['jurisdiction'] ?? 'United Republic of Tanzania' }}.</li>
    @endif
    @if ($show('data_privacy'))
        <li>Personal data is processed in accordance with applicable data protection laws and the lender's privacy policy.</li>
    @endif
    <li>Electronic and OTP signatures captured during application and acceptance form part of this contract.</li>
</ol>
@endif

@if ($show('signatures'))
<div class="signbox">
    <div class="sign-row">
        <div class="sign-col">
            <strong>Borrower</strong>
            @if (!empty($snapshot['borrower_signature']))
                <div style="margin-top:4px"><img src="{{ $snapshot['borrower_signature']->signature_data }}" class="sig-img"></div>
                <div class="muted">{{ $snapshot['borrower_signature']->signer_name }}</div>
            @elseif ($agreement->isSigned() && $agreement->document_type === 'loan_contract')
                <div class="muted" style="margin-top:8px">OTP accepted {{ $agreement->signed_at?->format('d M Y H:i') }}</div>
            @endif
        </div>
        <div class="sign-col">
            <strong>Guarantor</strong>
            @if (!empty($snapshot['guarantor_signature']))
                <div style="margin-top:4px"><img src="{{ $snapshot['guarantor_signature']->signature_data }}" class="sig-img"></div>
                <div class="muted">{{ $snapshot['guarantor_signature']->signer_name }}</div>
            @else
                <div class="muted" style="margin-top:8px">If applicable</div>
            @endif
        </div>
        <div class="sign-col">
            <strong>{{ brand('legal_name') }}</strong>
            @if (! empty($snapshot['company_signature_path']))
                <div style="margin-top:4px"><img src="{{ $snapshot['company_signature_path'] }}" class="sig-img"></div>
            @endif
            <div class="muted" style="margin-top:4px">{{ $snapshot['company_signatory_name'] ?? brand('legal_name') }}</div>
            @if (! empty($snapshot['company_signatory_title']))
                <div class="muted">{{ $snapshot['company_signatory_title'] }}</div>
            @endif
        </div>
        <div class="sign-col" style="text-align:center">
            <strong>Company stamp</strong>
            @if (! empty($snapshot['company_stamp_path']))
                <div><img src="{{ $snapshot['company_stamp_path'] }}" class="stamp-img" alt="Stamp"></div>
            @else
                <div class="muted" style="margin-top:12px">—</div>
            @endif
        </div>
    </div>
    @if ($agreement->isSigned())
        <div class="muted" style="margin-top:10px">Executed {{ $agreement->signed_at->format('d M Y H:i') }}</div>
    @endif
</div>
@endif

</body>
</html>
