<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>{{ __('borrower.offer_letter.pdf.title', ['reference' => $agreement->reference]) }}</title>
<style>
    @page { margin: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1c2b24; line-height: 1.55; margin: 0; }
    .band { background: #0f3d2e; color: #fff; padding: 22px 28px 18px; }
    .band h1 { font-size: 20px; margin: 0; letter-spacing: 0.5px; color: #f5c842; }
    .band .tag { font-size: 10px; color: rgba(255,255,255,0.75); margin-top: 4px; }
    .gold-bar { height: 4px; background: #f5c842; }
    .wrap { padding: 22px 28px 28px; }
    h2 { font-size: 12px; margin: 18px 0 8px; color: #0f3d2e; text-transform: uppercase; letter-spacing: 1.2px; border-bottom: 1px solid #e5ebe7; padding-bottom: 4px; }
    .muted { color: #6b7c74; font-size: 10px; }
    .pill { display: inline-block; padding: 3px 10px; border-radius: 999px; background: #f5c842; color: #0f3d2e; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
    table.kv { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 7px 0; vertical-align: top; border-bottom: 1px solid #eef2ef; }
    table.kv td.label { color: #6b7c74; width: 40%; font-size: 10px; text-transform: uppercase; letter-spacing: 0.4px; }
    table.kv td.value { color: #12241c; font-weight: 600; }
    .notice { margin-top: 16px; padding: 12px 14px; background: #f7faf8; border-left: 3px solid #f5c842; font-size: 10.5px; }
    .footer { margin-top: 22px; font-size: 9px; color: #8a9a92; border-top: 1px solid #e5ebe7; padding-top: 8px; }
    .sig-img { max-height: 46px; max-width: 150px; margin: 8px 0 4px; }
    .stamp-img { max-height: 70px; max-width: 70px; margin-top: 4px; }
    .meta { text-align: right; }
    .logo { max-height: 36px; margin-bottom: 8px; }
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
    $logo = public_path('images/brand/kopafasta-logo.png');
@endphp

<div class="band">
    <table style="width:100%"><tr>
        <td>
            @if (is_file($logo))
                <img src="{{ $logo }}" class="logo" alt="">
            @endif
            <h1>{{ brand('legal_name') }}</h1>
            <div class="tag">{{ __('borrower.offer_letter.pdf.tagline') }}</div>
        </td>
        <td class="meta">
            <div class="pill">{{ __('borrower.offer_letter.pdf.pill') }}</div>
            <div class="tag" style="margin-top:8px;color:rgba(255,255,255,0.85)">{{ __('borrower.offer_letter.pdf.reference') }}: <strong>{{ $agreement->reference }}</strong></div>
            <div class="tag" style="color:rgba(255,255,255,0.85)">{{ __('borrower.offer_letter.pdf.date') }}: {{ now()->format('d M Y') }}</div>
        </td>
    </tr></table>
</div>
<div class="gold-bar"></div>

<div class="wrap">
    <p>{{ __('borrower.offer_letter.pdf.greeting', ['name' => $snapshot['customer_name'] ?: __('borrower.offer_letter.pdf.customer_fallback')]) }}</p>
    <p>{{ __('borrower.offer_letter.pdf.intro') }}</p>

    @if (! empty($snapshot['is_group_loan']) && ! empty($snapshot['group_members']))
        <h2>{{ __('borrower.offer_letter.pdf.group_heading') }}</h2>
        <p class="muted">{{ $snapshot['group_name'] ?? '' }}</p>
        <table class="kv">
            @foreach ($snapshot['group_members'] as $member)
                <tr>
                    <td class="label">{{ $member['name'] ?? '—' }}{{ ($member['role'] ?? '') === 'leader' ? ' · '.__('borrower.offer_letter.pdf.leader') : '' }}</td>
                    <td class="value">{{ format_money($member['requested_amount'] ?? 0) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>{{ __('borrower.offer_letter.pdf.facility_heading') }}</h2>
    <table class="kv">
        <tr><td class="label">{{ __('borrower.offer_letter.pdf.application_number') }}</td><td class="value">{{ $snapshot['application_number'] }}</td></tr>
        <tr><td class="label">{{ __('borrower.offer_letter.pdf.product') }}</td><td class="value">{{ $snapshot['product_name'] }} ({{ $snapshot['product_code'] }})</td></tr>
        <tr><td class="label">{{ __('borrower.offer_letter.pdf.approved_amount') }}</td><td class="value">{{ format_money($snapshot['principal']) }}</td></tr>
        <tr><td class="label">{{ ! empty($snapshot['hides_interest']) ? __('borrower.offer_letter.pdf.charge_rate') : __('borrower.offer_letter.pdf.interest_rate') }}</td><td class="value">{{ ! empty($snapshot['hides_interest']) ? __('borrower.offer_letter.pdf.charge_rate_value', ['rate' => format_number(($snapshot['displayed_monthly_rate'] ?? $snapshot['interest_rate'] ?? 0) * 100, 2)]) : __('borrower.offer_letter.pdf.interest_rate_value', ['rate' => format_number(($snapshot['displayed_monthly_rate'] ?? $snapshot['interest_rate'] ?? 0) * 100, 2)]) }}</td></tr>
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

    <table style="width:100%;margin-top:28px">
        <tr>
            <td style="width:55%;vertical-align:top">
                <strong>{{ __('borrower.offer_letter.pdf.for_company', ['company' => brand('legal_name')]) }}</strong>
                @if (! empty($snapshot['company_signature_path']))
                    <div><img src="{{ $snapshot['company_signature_path'] }}" class="sig-img" alt=""></div>
                @else
                    <div style="height:36px"></div>
                @endif
                <div class="muted">{{ $snapshot['company_signatory_name'] ?? brand('legal_name') }}</div>
                @if (! empty($snapshot['company_signatory_title']))
                    <div class="muted">{{ $snapshot['company_signatory_title'] }}</div>
                @endif
            </td>
            <td style="width:45%;vertical-align:top;text-align:center">
                <strong>{{ __('borrower.offer_letter.pdf.company_stamp') }}</strong>
                @if (! empty($snapshot['company_stamp_path']))
                    <div><img src="{{ $snapshot['company_stamp_path'] }}" class="stamp-img" alt=""></div>
                @endif
                <div class="muted" style="margin-top:6px">{{ __('borrower.offer_letter.pdf.company_stamp_only') }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ __('borrower.offer_letter.pdf.footer', ['company' => brand('legal_name'), 'email' => brand('support_email')]) }}
    </div>
</div>
</body>
</html>
