<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>{{ __('borrower.rejection_letter.pdf.title', ['reference' => $agreement->reference]) }}</title>
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
    .notice { margin-top: 14px; padding: 12px 14px; background: #faf6f1; border-left: 3px solid #b45309; font-size: 10.5px; }
    .fail-box { margin-top: 12px; padding: 12px 14px; background: #fff7f7; border: 1px solid #fecaca; font-size: 10.5px; }
    .fail-box li { margin: 4px 0; }
    .advice { margin-top: 12px; padding: 12px 14px; background: #f7faf8; border-left: 3px solid #f5c842; font-size: 10.5px; }
    .footer { margin-top: 22px; font-size: 9px; color: #8a9a92; border-top: 1px solid #e5ebe7; padding-top: 8px; }
    .sig-img { max-height: 46px; max-width: 150px; margin: 8px 0 4px; }
    .stamp-img { max-height: 70px; max-width: 70px; margin-top: 4px; }
    .meta { text-align: right; }
    .logo { max-height: 36px; margin-bottom: 8px; }
</style>
</head>
<body>
@php
    $logo = public_path('images/brand/kopafasta-mark.png');
    if (! is_file($logo)) {
        $logo = public_path('images/brand/kopafasta-logo.png');
    }
    $failed = $snapshot['failed_members'] ?? data_get($snapshot, 'capacity_auto_reject.failed_members', []);
@endphp

<div class="band">
    <table style="width:100%"><tr>
        <td>
            @if (is_file($logo))
                <img src="{{ $logo }}" class="logo" alt="">
            @endif
            <h1>{{ brand('legal_name') }}</h1>
            <div class="tag">{{ __('borrower.rejection_letter.pdf.tagline') }}</div>
        </td>
        <td class="meta">
            <div class="pill">{{ __('borrower.rejection_letter.pdf.pill') }}</div>
            <div class="tag" style="margin-top:8px;color:rgba(255,255,255,0.85)">{{ __('borrower.rejection_letter.pdf.reference') }}: <strong>{{ $agreement->reference }}</strong></div>
            <div class="tag" style="color:rgba(255,255,255,0.85)">{{ __('borrower.rejection_letter.pdf.date') }}: {{ \Illuminate\Support\Carbon::parse($snapshot['rejected_at'] ?? now())->format('d M Y') }}</div>
        </td>
    </tr></table>
</div>
<div class="gold-bar"></div>

<div class="wrap">
    <p>{{ __('borrower.rejection_letter.pdf.greeting', ['name' => $snapshot['customer_name'] ?: __('borrower.rejection_letter.pdf.customer_fallback')]) }}</p>
    <p>{{ __('borrower.rejection_letter.pdf.intro') }}</p>

    <h2>{{ __('borrower.rejection_letter.pdf.details_heading') }}</h2>
    <table class="kv">
        <tr><td class="label">{{ __('borrower.rejection_letter.pdf.application_number') }}</td><td class="value">{{ $snapshot['application_number'] }}</td></tr>
        <tr><td class="label">{{ __('borrower.rejection_letter.pdf.product') }}</td><td class="value">{{ $snapshot['product_name'] }} ({{ $snapshot['product_code'] }})</td></tr>
        <tr><td class="label">{{ __('borrower.rejection_letter.pdf.requested_amount') }}</td><td class="value">{{ format_money($snapshot['requested_amount'] ?? $snapshot['principal'] ?? 0) }}</td></tr>
        @if (! empty($snapshot['tenure_months']))
            <tr><td class="label">{{ __('borrower.rejection_letter.pdf.tenure') }}</td><td class="value">{{ __('borrower.rejection_letter.pdf.tenure_months', ['months' => $snapshot['tenure_months']]) }}</td></tr>
        @endif
    </table>

    <div class="notice">
        <strong>{{ __('borrower.rejection_letter.pdf.reason_heading') }}</strong><br>
        {{ $snapshot['rejection_reason'] ?? __('borrower.applications_list.rejected_default') }}
    </div>

    @if (! empty($failed))
        <div class="fail-box">
            <strong>{{ __('borrower.rejection_letter.pdf.failed_members_heading') }}</strong>
            <ul>
                @foreach ($failed as $member)
                    <li>
                        <strong>{{ $member['name'] ?? '—' }}</strong>
                        @if (($member['role'] ?? '') === 'leader')
                            ({{ __('borrower.rejection_letter.pdf.leader') }})
                        @endif
                        — {{ __('borrower.rejection_letter.pdf.member_share') }}: {{ format_money($member['requested_amount'] ?? 0) }}
                        · {{ __('borrower.rejection_letter.pdf.member_installment') }}: {{ format_money($member['proposed_installment'] ?? 0) }}
                        · {{ __('borrower.rejection_letter.pdf.member_capacity') }}: {{ format_money($member['available_capacity'] ?? 0) }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($snapshot['rejection_advice']))
        <div class="advice">
            <strong>{{ __('borrower.rejection_letter.pdf.advice_heading') }}</strong><br>
            {{ $snapshot['rejection_advice'] }}
        </div>
    @endif

    <table style="width:100%;margin-top:28px">
        <tr>
            <td style="width:55%;vertical-align:top">
                <strong>{{ __('borrower.rejection_letter.pdf.for_company', ['company' => brand('legal_name')]) }}</strong>
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
                <strong>{{ __('borrower.rejection_letter.pdf.company_stamp') }}</strong>
                @if (! empty($snapshot['company_stamp_path']))
                    <div><img src="{{ $snapshot['company_stamp_path'] }}" class="stamp-img" alt=""></div>
                @else
                    <div class="muted" style="margin-top:8px">{{ __('borrower.rejection_letter.pdf.stamp_missing') }}</div>
                @endif
                <div class="muted" style="margin-top:6px">{{ __('borrower.rejection_letter.pdf.company_stamp_only') }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ __('borrower.rejection_letter.pdf.footer', ['company' => brand('legal_name'), 'email' => brand('support_email')]) }}
    </div>
</div>
</body>
</html>
