<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $snapshot['locale'] ?? app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>{{ pdf_text(__('borrower.rejection_letter.pdf.title', ['reference' => $agreement->reference], $snapshot['locale'] ?? app()->getLocale())) }}</title>
@include('pdf.loan-agreement._styles')
<style>
    .notice { background: #faf6f1; border-left: 3px solid #b45309; }
    .notice ul { margin: 8px 0 0; padding-left: 18px; }
    .notice li { margin: 4px 0; }
    .fail-box { margin-top: 12px; padding: 12px 14px; background: #fff7f7; border: 1px solid #fecaca; font-size: 10.5px; }
    .fail-box li { margin: 4px 0; }
    .advice { margin-top: 12px; padding: 12px 14px; background: #f7faf8; border-left: 3px solid #f5c842; font-size: 10.5px; }
</style>
</head>
<body>
@php require resource_path('views/pdf/loan-agreement/locale.php'); @endphp
@php
    $failed = $snapshot['failed_members'] ?? data_get($snapshot, 'capacity_auto_reject.failed_members', []);
    $reasonList = array_values(array_filter($snapshot['rejection_reasons'] ?? []));
    if ($reasonList === [] && filled($snapshot['rejection_reason'] ?? null)) {
        $reasonList = [(string) $snapshot['rejection_reason']];
    }
    $reasonHeading = count($reasonList) > 1
        ? $t('Reasons for this decision', 'Sababu za uamuzi huu')
        : pdf_text(__('borrower.rejection_letter.pdf.reason_heading', [], $locale));
    $clauses = $snapshot['legal_clauses'] ?? [];
    $jurisdiction = $jurisdictionLabel($snapshot['jurisdiction'] ?? ($clauses['jurisdiction'] ?? null));
    $tagline = filled($snapshot['jurisdiction'] ?? null) || filled($clauses['jurisdiction'] ?? null)
        ? ($isSw ? 'Huduma za mikopo ndogo — ' : 'Microfinance Services — ').$jurisdiction
        : pdf_text(__('borrower.rejection_letter.pdf.tagline', [], $locale));
    $company = $snapshot['company_legal_name'] ?? brand('legal_name');
    $complaintsEmail = $snapshot['complaints_email'] ?? brand('support_email');
    $capacity = is_array($snapshot['capacity_auto_reject'] ?? null) ? $snapshot['capacity_auto_reject'] : [];
@endphp

@include('pdf._brand_band', [
    'bandTitle' => $company,
    'bandTag' => $tagline,
    'bandMeta' => '<div class="pill">'.e(pdf_text(__('borrower.rejection_letter.pdf.pill', [], $locale))).'</div>'
        .'<div class="tag" style="margin-top:8px">'.e(pdf_text(__('borrower.rejection_letter.pdf.reference', [], $locale))).': <strong>'.e($agreement->reference).'</strong></div>'
        .'<div class="tag">'.e(pdf_text(__('borrower.rejection_letter.pdf.date', [], $locale))).': '.e(\Illuminate\Support\Carbon::parse($snapshot['rejected_at'] ?? now())->format('d M Y')).'</div>',
])

<div class="wrap">
    <p>{{ pdf_text(__('borrower.rejection_letter.pdf.greeting', ['name' => $snapshot['customer_name'] ?: __('borrower.rejection_letter.pdf.customer_fallback', [], $locale)], $locale)) }}</p>
    <p>{{ pdf_text(__('borrower.rejection_letter.pdf.intro', [], $locale)) }}</p>

    <h2>{{ pdf_text(__('borrower.rejection_letter.pdf.details_heading', [], $locale)) }}</h2>
    <table class="kv">
        <tr><td class="label">{{ pdf_text(__('borrower.rejection_letter.pdf.application_number', [], $locale)) }}</td><td class="value">{{ $snapshot['application_number'] }}</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.rejection_letter.pdf.product', [], $locale)) }}</td><td class="value">{{ pdf_text($snapshot['product_name']) }} ({{ $snapshot['product_code'] }})</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.rejection_letter.pdf.requested_amount', [], $locale)) }}</td><td class="value">{{ format_money($snapshot['requested_amount'] ?? $snapshot['principal'] ?? 0) }}</td></tr>
        @if (! empty($snapshot['tenure_months']))
            <tr><td class="label">{{ pdf_text(__('borrower.rejection_letter.pdf.tenure', [], $locale)) }}</td><td class="value">{{ pdf_text(__('borrower.rejection_letter.pdf.tenure_months', ['months' => $snapshot['tenure_months']], $locale)) }}</td></tr>
        @endif
    </table>

    <div class="notice">
        <strong>{{ $reasonHeading }}</strong>
        @if ($reasonList !== [])
            <ul>
                @foreach ($reasonList as $reason)
                    <li>{{ pdf_text($reason) }}</li>
                @endforeach
            </ul>
        @else
            <p>{{ pdf_text(__('borrower.applications_list.rejected_default', [], $locale)) }}</p>
        @endif
        @if (! empty($snapshot['rejection_detail']))
            <p>{{ pdf_text($snapshot['rejection_detail']) }}</p>
        @endif
    </div>

    @if ($capacity !== [] && $failed === [])
        <table class="kv" style="margin-top:12px">
            @if (isset($capacity['requested_amount']))
                <tr><td class="label">{{ $t('Amount requested', 'Kiasi kilichoombwa') }}</td><td class="value">{{ format_money($capacity['requested_amount']) }}</td></tr>
            @endif
            @if (isset($capacity['proposed_installment']))
                <tr><td class="label">{{ $t('Proposed instalment', 'Malipo yaliyopendekezwa') }}</td><td class="value">{{ format_money($capacity['proposed_installment']) }}</td></tr>
            @endif
            @if (isset($capacity['available_capacity']))
                <tr><td class="label">{{ $t('Available capacity', 'Uwezo unaopatikana') }}</td><td class="value">{{ format_money($capacity['available_capacity']) }}</td></tr>
            @endif
        </table>
    @endif

    @if (! empty($failed))
        <div class="fail-box">
            <strong>{{ pdf_text(__('borrower.rejection_letter.pdf.failed_members_heading', [], $locale)) }}</strong>
            <ul>
                @foreach ($failed as $member)
                    <li>
                        <strong>{{ $member['name'] ?? '—' }}</strong>
                        @if (($member['role'] ?? '') === 'leader')
                            ({{ pdf_text(__('borrower.rejection_letter.pdf.leader', [], $locale)) }})
                        @endif
                        — {{ pdf_text(__('borrower.rejection_letter.pdf.member_share', [], $locale)) }}: {{ format_money($member['requested_amount'] ?? 0) }}
                        · {{ pdf_text(__('borrower.rejection_letter.pdf.member_installment', [], $locale)) }}: {{ format_money($member['proposed_installment'] ?? 0) }}
                        · {{ pdf_text(__('borrower.rejection_letter.pdf.member_capacity', [], $locale)) }}: {{ format_money($member['available_capacity'] ?? 0) }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($snapshot['rejection_advice']))
        <div class="advice">
            <strong>{{ pdf_text(__('borrower.rejection_letter.pdf.advice_heading', [], $locale)) }}</strong><br>
            {{ pdf_text($snapshot['rejection_advice']) }}
        </div>
    @endif

    <table style="width:100%;margin-top:28px">
        <tr>
            <td style="width:55%;vertical-align:top">
                <strong>{{ pdf_text(__('borrower.rejection_letter.pdf.for_company', ['company' => $company], $locale)) }}</strong>
                @if (! empty($snapshot['company_signature_path']))
                    <div><img src="{{ $snapshot['company_signature_path'] }}" class="sig-img" alt=""></div>
                @else
                    <div style="height:36px"></div>
                @endif
                <div class="muted">{{ $snapshot['company_signatory_name'] ?? $company }}</div>
                @if (! empty($snapshot['company_signatory_title']))
                    <div class="muted">{{ $snapshot['company_signatory_title'] }}</div>
                @endif
            </td>
            <td style="width:45%;vertical-align:top;text-align:center">
                <strong>{{ pdf_text(__('borrower.rejection_letter.pdf.company_stamp', [], $locale)) }}</strong>
                @if (! empty($snapshot['company_stamp_path']))
                    <div><img src="{{ $snapshot['company_stamp_path'] }}" class="stamp-img" alt=""></div>
                @else
                    <div class="muted" style="margin-top:8px">{{ pdf_text(__('borrower.rejection_letter.pdf.stamp_missing', [], $locale)) }}</div>
                @endif
                <div class="muted" style="margin-top:6px">{{ pdf_text(__('borrower.rejection_letter.pdf.company_stamp_only', [], $locale)) }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ pdf_text(__('borrower.rejection_letter.pdf.footer', ['company' => $company, 'email' => $complaintsEmail], $locale)) }}
    </div>
</div>
</body>
</html>
