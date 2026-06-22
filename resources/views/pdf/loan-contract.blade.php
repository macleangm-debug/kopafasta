<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>{{ __('borrower.loan_contract.pdf.title', ['reference' => $agreement->reference]) }}</title>
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
    $cadences = __('borrower.agreement.repayment_cadences');
    $cadenceKey = $snapshot['repayment_cadence'] ?? 'weekly';
    $cadenceLabel = $cadences[$cadenceKey] ?? ucfirst($cadenceKey);
@endphp

<div class="header">
    <h1>{{ brand('legal_name') }} — {{ __('borrower.contract.page_title') }}</h1>
    <div class="muted">{{ __('borrower.loan_contract.pdf.reference_line', ['reference' => $agreement->reference, 'application' => $snapshot['application_number']]) }}</div>
</div>

@if ($show('definitions'))
<p>
    {{ __('borrower.loan_contract.pdf.intro', [
        'date' => now()->format('d M Y'),
        'lender' => brand('legal_name'),
        'borrower' => $snapshot['customer_name'],
    ]) }}
</p>

<h2>{{ __('borrower.loan_contract.pdf.borrower_heading') }}</h2>
<table class="kv">
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.name') }}</td><td class="value">{{ $snapshot['customer_name'] }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.nida_number') }}</td><td class="value">{{ $snapshot['customer_id'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.address') }}</td><td class="value">{{ $snapshot['customer_address'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.activity') }}</td><td class="value">{{ $snapshot['customer_activity'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.income') }}</td><td class="value">{{ $snapshot['customer_income'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.phone') }}</td><td class="value">{{ $snapshot['customer_phone'] ?? '—' }}</td></tr>
</table>

@if ($show('guarantor_obligations') && ! empty($snapshot['guarantor_name']))
<h2>{{ __('borrower.loan_contract.pdf.guarantor_heading') }}</h2>
<table class="kv">
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.name') }}</td><td class="value">{{ $snapshot['guarantor_name'] }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.nida_number') }}</td><td class="value">{{ $snapshot['guarantor_nida'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.address') }}</td><td class="value">{{ $snapshot['guarantor_address'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.phone') }}</td><td class="value">{{ $snapshot['guarantor_phone'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.relationship') }}</td><td class="value">{{ $snapshot['guarantor_relationship'] ?? '—' }}</td></tr>
</table>
@endif
@endif

@if (!empty($snapshot['is_group_loan']) && !empty($snapshot['group_members']))
<h2>{{ __('borrower.loan_contract.pdf.group_heading') }}</h2>
<table class="kv">
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.group_name') }}</td><td class="value">{{ $snapshot['group_name'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.total_group_liability') }}</td><td class="value">{{ format_money($snapshot['total_group_liability'] ?? 0) }}</td></tr>
</table>
<table class="schedule" style="margin-top:8px">
    <thead>
        <tr>
            <th>{{ __('borrower.loan_contract.pdf.name') }}</th>
            <th>{{ __('borrower.loan_contract.pdf.nida_number') }}</th>
            <th>{{ __('borrower.loan_contract.pdf.phone') }}</th>
            <th>{{ __('borrower.loan_contract.pdf.member_allocation') }}</th>
            <th>{{ __('borrower.loan_contract.pdf.signature_status') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($snapshot['group_members'] as $groupMember)
        <tr>
            <td>{{ $groupMember['name'] }} @if(($groupMember['role'] ?? '') === 'leader') ({{ __('borrower.loan_contract.pdf.group_leader') }}) @endif</td>
            <td>{{ $groupMember['national_id'] ?? '—' }}</td>
            <td>{{ $groupMember['phone'] ?? '—' }}</td>
            <td>{{ format_money($groupMember['requested_amount'] ?? 0) }}</td>
            <td>{{ ucfirst($groupMember['signature_status'] ?? 'pending') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@if ($show('loan_terms'))
<h2>{{ __('borrower.loan_contract.pdf.facility_heading') }}</h2>
<table class="kv">
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.reference') }}</td><td class="value">{{ $agreement->reference }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.amount') }}</td><td class="value">{{ format_money($snapshot['principal']) }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.interest') }}</td><td class="value">{{ __('borrower.loan_contract.pdf.interest_value', ['rate' => format_number(($snapshot['displayed_monthly_rate'] ?? 0) * 100, 2)]) }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.tenure') }}</td><td class="value">{{ __('borrower.loan_contract.pdf.tenure_months', ['months' => $snapshot['tenure_months']]) }}</td></tr>
    <tr><td class="label">{{ $snapshot['installment_label'] ?? __('borrower.loan_contract.pdf.installment_amount') }}</td><td class="value">{{ format_money($snapshot['estimated_emi']) }}</td></tr>
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.total_repayable') }}</td><td class="value">{{ format_money($snapshot['total_repayable'] ?? 0) }}</td></tr>
</table>
@endif

@if ($show('repayment_obligations'))
<h2>{{ __('borrower.loan_contract.pdf.repayment_heading') }}</h2>
<p>
    {{ __('borrower.loan_contract.pdf.repayment_body', [
        'days' => (int) ($snapshot['repayment_commencement_days'] ?? 7),
        'cadence' => $cadenceLabel,
        'count' => $snapshot['installment_count'] ?? count($snapshot['repayment_schedule'] ?? []),
        'amount' => format_money($snapshot['estimated_emi'] ?? 0),
    ]) }}
</p>
<p class="muted">{{ __('borrower.loan_contract.pdf.repayment_note') }}</p>
@endif

@if (!empty($snapshot['is_asset_loan']))
<h2>{{ __('borrower.loan_contract.pdf.asset_heading') }}</h2>
<table class="kv">
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.financed_asset') }}</td><td class="value">{{ $snapshot['asset_title'] ?: __('borrower.loan_contract.pdf.asset_fallback') }}</td></tr>
    @if (!empty($snapshot['asset_supplier']))
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.supplier') }}</td><td class="value">{{ $snapshot['asset_supplier'] }}</td></tr>
    @endif
    @if (!empty($snapshot['asset_serial_number']))
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.serial_registration') }}</td><td class="value">{{ $snapshot['asset_serial_number'] }}</td></tr>
    @endif
    @if (!empty($snapshot['asset_chassis_number']))
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.chassis_number') }}</td><td class="value">{{ $snapshot['asset_chassis_number'] }}</td></tr>
    @endif
    @if (!empty($snapshot['asset_engine_number']))
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.engine_number') }}</td><td class="value">{{ $snapshot['asset_engine_number'] }}</td></tr>
    @endif
    @if (!empty($snapshot['asset_insurance_policy']))
    <tr><td class="label">{{ __('borrower.loan_contract.pdf.insurance_policy') }}</td><td class="value">{{ $snapshot['asset_insurance_policy'] }}</td></tr>
    @endif
</table>
@if (!empty($snapshot['collateral_description']))
<p><strong>{{ __('borrower.loan_contract.pdf.description') }}:</strong> {{ $snapshot['collateral_description'] }}</p>
@endif
@if (!empty($snapshot['collateral_market_value']))
<p><strong>{{ __('borrower.loan_contract.pdf.market_value') }}:</strong> {{ format_money($snapshot['collateral_market_value']) }}</p>
@endif
@if (!empty($snapshot['collateral_forced_sale_value']))
<p><strong>{{ __('borrower.loan_contract.pdf.forced_sale_value') }}:</strong> {{ format_money($snapshot['collateral_forced_sale_value']) }}</p>
@endif
@if (!empty($snapshot['collateral_gps_required']))
<p><strong>{{ __('borrower.loan_contract.pdf.gps_tracking') }}:</strong> {{ __('borrower.loan_contract.pdf.gps_required') }}</p>
@endif
<p>{{ $snapshot['asset_ownership_note'] }}</p>
@endif

@if ($show('penalty_clauses'))
<h2>{{ __('borrower.loan_contract.pdf.charges_heading') }}</h2>
<table class="charges">
    <tr><td>{{ __('borrower.loan_contract.pdf.penalty_rate') }}</td><td>{{ $clauses['penalty_rate_label'] ?? __('borrower.loan_contract.pdf.schedule_default') }}</td></tr>
    <tr><td>{{ __('borrower.loan_contract.pdf.grace_period') }}</td><td>{{ __('borrower.loan_contract.pdf.grace_period_value', ['days' => (int) ($clauses['grace_days'] ?? 7)]) }}</td></tr>
    <tr><td>{{ __('borrower.loan_contract.pdf.penalty_cap') }}</td><td>{{ __('borrower.loan_contract.pdf.penalty_cap_value', ['percent' => format_number($clauses['penalty_cap_percent'] ?? 30, 0)]) }}</td></tr>
    <tr><td>{{ __('borrower.loan_contract.pdf.late_fee') }}</td><td>{{ $clauses['late_fee_label'] ?? format_money(2000) }}</td></tr>
    <tr><td>{{ __('borrower.loan_contract.pdf.collection_charge') }}</td><td>{{ $clauses['collection_charge'] ?? __('borrower.loan_contract.pdf.collection_default') }}</td></tr>
    <tr><td>{{ __('borrower.loan_contract.pdf.legal_recovery') }}</td><td>{{ $clauses['legal_recovery'] ?? __('borrower.loan_contract.pdf.legal_recovery_default') }}</td></tr>
</table>
@endif

@if ($show('default_events') || $show('recovery_clauses') || $show('legal_costs') || $show('jurisdiction') || $show('data_privacy'))
<h2>{{ __('borrower.loan_contract.pdf.legal_heading') }}</h2>
<ol class="terms">
    @if ($show('default_events'))
        <li><strong>{{ __('borrower.loan_contract.pdf.default') }}:</strong> {{ $clauses['default_clause'] ?? '' }}</li>
        <li><strong>{{ __('borrower.loan_contract.pdf.collection') }}:</strong> {{ $clauses['collection_clause'] ?? '' }}</li>
    @endif
    @if ($show('recovery_clauses'))
        <li><strong>{{ __('borrower.loan_contract.pdf.recovery') }}:</strong> {{ $clauses['recovery_clause'] ?? '' }}</li>
    @endif
    @if ($show('penalty_clauses'))
        <li><strong>{{ __('borrower.loan_contract.pdf.penalty_charges') }}:</strong> {{ $clauses['penalty_clause'] ?? '' }}</li>
    @endif
    @if ($show('legal_costs'))
        <li><strong>{{ __('borrower.loan_contract.pdf.legal_costs') }}:</strong> {{ $clauses['legal_cost_clause'] ?? '' }}</li>
    @endif
    @if (! empty($snapshot['is_asset_loan']))
        <li><strong>{{ __('borrower.loan_contract.pdf.asset_recovery') }}:</strong> {{ $clauses['asset_recovery_clause'] ?? '' }}</li>
    @endif
    @if ($show('guarantor_obligations') && ! empty($snapshot['guarantor_name']))
        <li><strong>{{ __('borrower.loan_contract.pdf.guarantor_liability') }}:</strong> {{ $clauses['guarantor_clause'] ?? '' }}</li>
    @endif
    @if ($show('jurisdiction'))
        <li><strong>{{ __('borrower.loan_contract.pdf.jurisdiction') }}:</strong> {{ __('borrower.loan_contract.pdf.jurisdiction_body', ['country' => $clauses['jurisdiction'] ?? __('borrower.loan_contract.pdf.lender_default')]) }}</li>
    @endif
    @if ($show('data_privacy'))
        <li>{{ __('borrower.loan_contract.pdf.data_privacy') }}</li>
    @endif
    <li>{{ __('borrower.loan_contract.pdf.electronic_signatures') }}</li>
</ol>
@endif

@if ($show('signatures'))
<div class="signbox">
    <div class="sign-row">
        <div class="sign-col">
            <strong>{{ __('borrower.loan_contract.pdf.borrower') }}</strong>
            @if (!empty($snapshot['borrower_signature']))
                <div style="margin-top:4px"><img src="{{ $snapshot['borrower_signature']->signature_data }}" class="sig-img"></div>
                <div class="muted">{{ $snapshot['borrower_signature']->signer_name }}</div>
            @elseif ($agreement->isSigned() && $agreement->document_type === 'loan_contract')
                <div class="muted" style="margin-top:8px">{{ __('borrower.loan_contract.pdf.otp_accepted', ['date' => $agreement->signed_at?->format('d M Y H:i')]) }}</div>
            @endif
        </div>
        <div class="sign-col">
            <strong>{{ __('borrower.loan_contract.pdf.guarantor') }}</strong>
            @if (!empty($snapshot['guarantor_signature']))
                <div style="margin-top:4px"><img src="{{ $snapshot['guarantor_signature']->signature_data }}" class="sig-img"></div>
                <div class="muted">{{ $snapshot['guarantor_signature']->signer_name }}</div>
            @else
                <div class="muted" style="margin-top:8px">{{ __('borrower.loan_contract.pdf.if_applicable') }}</div>
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
            <strong>{{ __('borrower.loan_contract.pdf.company_stamp') }}</strong>
            @if (! empty($snapshot['company_stamp_path']))
                <div><img src="{{ $snapshot['company_stamp_path'] }}" class="stamp-img" alt="Stamp"></div>
            @else
                <div class="muted" style="margin-top:12px">—</div>
            @endif
        </div>
    </div>
    @if ($agreement->isSigned())
        <div class="muted" style="margin-top:10px">{{ __('borrower.loan_contract.pdf.executed', ['date' => $agreement->signed_at->format('d M Y H:i')]) }}</div>
    @endif
</div>
@endif

</body>
</html>
