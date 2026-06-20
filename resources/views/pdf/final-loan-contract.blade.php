<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Final Loan Contract — {{ $agreement->reference }}</title>
<style>
    @page { margin: 20mm 12mm; }
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
    table.schedule th { background: #f9fafb; text-transform: uppercase; font-size: 8px; }
    .terms li { margin-bottom: 4px; }
    .charges { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 6px; }
    .charges td { padding: 4px 6px; border: 1px solid #e5e7eb; }
    .charges td:first-child { background: #f9fafb; width: 38%; font-weight: 600; }
    .signbox { margin-top: 20px; padding: 12px; border: 1px dashed #b45309; background: #fffbeb; }
    .sign-row { display: table; width: 100%; table-layout: fixed; }
    .sign-col { width: 24%; display: inline-block; vertical-align: bottom; padding-right: 1%; }
    .sig-img { max-height: 72px; max-width: 180px; }
    .stamp-img { max-height: 72px; max-width: 72px; margin-top: 4px; }
    .annex { page-break-before: always; }
</style>
</head>
<body>

@php
    $clauses = $snapshot['legal_clauses'] ?? [];
    $signedContract = $signedContract ?? null;
@endphp

<div class="header">
    <h1>{{ brand('legal_name') }} — Final Loan Contract</h1>
    <div class="muted">Reference: {{ $agreement->reference }} · Loan: {{ $loan->loan_number ?? $loan->id }} · Disbursed {{ $snapshot['disbursement_date'] ? \Illuminate\Support\Carbon::parse($snapshot['disbursement_date'])->format('d M Y') : '—' }}</div>
</div>

<p>
    This executed loan agreement incorporates the terms accepted by the borrower and the repayment schedule annex
    following disbursement on <strong>{{ $snapshot['disbursement_date'] ? \Illuminate\Support\Carbon::parse($snapshot['disbursement_date'])->format('d M Y') : '—' }}</strong>.
</p>

<h2>Borrower</h2>
<table class="kv">
    <tr><td class="label">Full name</td><td class="value">{{ $snapshot['customer_name'] }}</td></tr>
    <tr><td class="label">NIDA number</td><td class="value">{{ $snapshot['customer_id'] ?? '—' }}</td></tr>
    <tr><td class="label">Address</td><td class="value">{{ $snapshot['customer_address'] ?? '—' }}</td></tr>
    <tr><td class="label">Activity</td><td class="value">{{ $snapshot['customer_activity'] ?? '—' }}</td></tr>
    <tr><td class="label">Income</td><td class="value">{{ $snapshot['customer_income'] ?? '—' }}</td></tr>
    <tr><td class="label">Phone</td><td class="value">{{ $snapshot['customer_phone'] ?? '—' }}</td></tr>
</table>

@if (! empty($snapshot['guarantor_name']))
<h2>Guarantor</h2>
<table class="kv">
    <tr><td class="label">Full name</td><td class="value">{{ $snapshot['guarantor_name'] }}</td></tr>
    <tr><td class="label">NIDA number</td><td class="value">{{ $snapshot['guarantor_nida'] ?? '—' }}</td></tr>
    <tr><td class="label">Address</td><td class="value">{{ $snapshot['guarantor_address'] ?? '—' }}</td></tr>
    <tr><td class="label">Phone</td><td class="value">{{ $snapshot['guarantor_phone'] ?? '—' }}</td></tr>
    <tr><td class="label">Relationship</td><td class="value">{{ $snapshot['guarantor_relationship'] ?? '—' }}</td></tr>
</table>
@endif

<h2>Loan facility</h2>
<table class="kv">
    <tr><td class="label">Reference</td><td class="value">{{ $signedContract?->reference ?? $agreement->reference }}</td></tr>
    <tr><td class="label">Amount</td><td class="value">{{ format_money($snapshot['principal']) }}</td></tr>
    <tr><td class="label">Interest</td><td class="value">{{ format_number(($snapshot['displayed_monthly_rate'] ?? 0) * 100, 2) }}% per month</td></tr>
    <tr><td class="label">Tenure</td><td class="value">{{ $snapshot['tenure_months'] }} months</td></tr>
    <tr><td class="label">{{ $snapshot['installment_label'] ?? 'Instalment' }}</td><td class="value">{{ format_money($snapshot['estimated_emi']) }}</td></tr>
    <tr><td class="label">Disbursement date</td><td class="value">{{ $snapshot['disbursement_date'] ? \Illuminate\Support\Carbon::parse($snapshot['disbursement_date'])->format('d M Y') : '—' }}</td></tr>
    <tr><td class="label">First instalment</td><td class="value">{{ $snapshot['first_due_date'] ? \Illuminate\Support\Carbon::parse($snapshot['first_due_date'])->format('d M Y') : '—' }}</td></tr>
    <tr><td class="label">Final instalment</td><td class="value">{{ $snapshot['last_due_date'] ? \Illuminate\Support\Carbon::parse($snapshot['last_due_date'])->format('d M Y') : '—' }}</td></tr>
</table>

@if (!empty($snapshot['is_asset_loan']))
<h2>Asset recovery</h2>
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
<p>{{ $clauses['asset_recovery_clause'] ?? $snapshot['asset_ownership_note'] ?? '' }}</p>
@endif

<h2>Charges &amp; penalties</h2>
<table class="charges">
    <tr><td>Penalty charges</td><td>{{ $clauses['penalty_rate_label'] ?? 'As per schedule of charges' }}</td></tr>
    <tr><td>Grace period</td><td>{{ (int) ($clauses['grace_days'] ?? 7) }} days after missed instalment</td></tr>
    <tr><td>Collection costs</td><td>{{ $clauses['collection_charge'] ?? 'Actual cost incurred' }}</td></tr>
    <tr><td>Legal costs</td><td>{{ $clauses['legal_recovery'] ?? 'Borrower responsible' }}</td></tr>
</table>

<h2>Legal clauses</h2>
<ol class="terms">
    <li><strong>Default:</strong> {{ $clauses['default_clause'] ?? '' }}</li>
    <li><strong>Recovery:</strong> {{ $clauses['recovery_clause'] ?? '' }}</li>
    <li><strong>Collection:</strong> {{ $clauses['collection_clause'] ?? '' }}</li>
    <li><strong>Penalty charges:</strong> {{ $clauses['penalty_clause'] ?? '' }}</li>
    <li><strong>Legal costs:</strong> {{ $clauses['legal_cost_clause'] ?? '' }}</li>
    @if (! empty($snapshot['guarantor_name']))
        <li><strong>Guarantor liability:</strong> {{ $clauses['guarantor_clause'] ?? '' }}</li>
    @endif
    <li><strong>Jurisdiction:</strong> {{ $clauses['jurisdiction'] ?? 'United Republic of Tanzania' }}</li>
</ol>

<div class="signbox">
    <div class="sign-row">
        <div class="sign-col">
            <strong>Borrower</strong>
            @if (!empty($snapshot['borrower_signature']))
                <div style="margin-top:4px"><img src="{{ $snapshot['borrower_signature']->signature_data }}" class="sig-img"></div>
                <div class="muted">{{ $snapshot['borrower_signature']->signer_name ?? $snapshot['customer_name'] }}</div>
            @else
                <div class="muted" style="margin-top:8px">{{ $snapshot['customer_name'] ?? '—' }}</div>
            @endif
        </div>
        <div class="sign-col">
            <strong>Guarantor</strong>
            @if (!empty($snapshot['guarantor_signature']))
                <div style="margin-top:4px"><img src="{{ $snapshot['guarantor_signature']->signature_data }}" class="sig-img"></div>
                <div class="muted">{{ $snapshot['guarantor_signature']->signer_name }}</div>
            @elseif (! empty($snapshot['guarantor_name']))
                <div class="muted" style="margin-top:8px">If applicable</div>
            @else
                <div class="muted" style="margin-top:8px">—</div>
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
    @if ($signedContract?->signed_at)
        <div class="muted" style="margin-top:10px">Contract executed {{ $signedContract->signed_at->format('d M Y H:i') }} · Final document generated {{ now()->format('d M Y') }}</div>
    @endif
</div>

<div class="annex">
    <h2>Annex A — Repayment schedule</h2>
    <p class="muted">Actual instalment dates following disbursement on {{ $snapshot['disbursement_date'] ? \Illuminate\Support\Carbon::parse($snapshot['disbursement_date'])->format('d M Y') : '—' }}.</p>
    <table class="schedule">
        <thead>
            <tr>
                <th>#</th>
                <th>Due date</th>
                <th>Amount</th>
                <th>Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($snapshot['repayment_schedule'] ?? []) as $row)
                <tr>
                    <td>{{ $row['installment_no'] }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['due_date'])->format('d M Y') }}</td>
                    <td>{{ format_money($row['total_due']) }}</td>
                    <td>{{ format_money($row['outstanding_balance'] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

</body>
</html>
