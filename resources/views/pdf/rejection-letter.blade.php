<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>{{ pdf_text(__('borrower.rejection_letter.pdf.title', ['reference' => $agreement->reference])) }}</title>
@include('pdf.loan-agreement._styles')
<style>
    .notice { background: #faf6f1; border-left: 3px solid #b45309; }
    .fail-box { margin-top: 12px; padding: 12px 14px; background: #fff7f7; border: 1px solid #fecaca; font-size: 10.5px; }
    .fail-box li { margin: 4px 0; }
    .advice { margin-top: 12px; padding: 12px 14px; background: #f7faf8; border-left: 3px solid #f5c842; font-size: 10.5px; }
</style>
</head>
<body>
@php
    $failed = $snapshot['failed_members'] ?? data_get($snapshot, 'capacity_auto_reject.failed_members', []);
@endphp

@include('pdf._brand_band', [
    'bandTitle' => $snapshot['company_legal_name'] ?? brand('legal_name'),
    'bandTag' => pdf_text(__('borrower.rejection_letter.pdf.tagline')),
    'bandMeta' => '<div class="pill">'.e(pdf_text(__('borrower.rejection_letter.pdf.pill'))).'</div>'
        .'<div class="tag" style="margin-top:8px">'.e(pdf_text(__('borrower.rejection_letter.pdf.reference'))).': <strong>'.e($agreement->reference).'</strong></div>'
        .'<div class="tag">'.e(pdf_text(__('borrower.rejection_letter.pdf.date'))).': '.e(\Illuminate\Support\Carbon::parse($snapshot['rejected_at'] ?? now())->format('d M Y')).'</div>',
])

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
