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
    .summary { margin-top: 14px; border: 1px solid #d7e3db; }
    .summary th { background: #0f3d2e; color: #fff; text-align: left; padding: 8px 10px; font-size: 10px; letter-spacing: 0.06em; text-transform: uppercase; }
    .summary td { padding: 7px 10px; font-size: 10.5px; border-top: 1px solid #e5efe9; }
    .summary td.label { width: 55%; color: #4b5563; }
    .summary td.value { font-weight: 700; color: #0f3d2e; }
    .preview-banner { margin: 10px 42px 0; padding: 10px 12px; background: #fff7ed; border-left: 3px solid #c2410c; font-size: 10.5px; color: #9a3412; }
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
    $clauses = $snapshot['legal_clauses'] ?? [];
    $jurisdiction = $jurisdictionLabel($snapshot['jurisdiction'] ?? ($clauses['jurisdiction'] ?? null));
    $company = $snapshot['company_legal_name'] ?? brand('legal_name');
    $complaintsEmail = $snapshot['complaints_email'] ?? brand('support_email');
    $complaintsPhone = $snapshot['company_phone'] ?? brand('support_phone');
    $capacity = is_array($snapshot['capacity_auto_reject'] ?? null) ? $snapshot['capacity_auto_reject'] : [];
    $isCapacity = ! empty($snapshot['is_capacity_rejection']);
    $ratioLabel = (string) ($snapshot['affordability_ratio'] ?? '');
    $incomeBasis = (string) ($snapshot['income_basis'] ?? 'declared');
    $incomeBasisPhrase = $incomeBasis === 'statement'
        ? pdf_text(__('borrower.rejection_letter.pdf.income_basis_verified', [], $locale))
        : pdf_text(__('borrower.rejection_letter.pdf.income_basis_profile', [], $locale));
    $decisionHeading = (string) ($snapshot['decision_heading'] ?? pdf_text(__('borrower.rejection_letter.pdf.decision_heading', [], $locale)));
    $tagline = filled($jurisdiction)
        ? pdf_text(__('borrower.rejection_letter.pdf.tagline_jurisdiction', ['jurisdiction' => $jurisdiction], $locale))
        : pdf_text(__('borrower.rejection_letter.pdf.tagline', [], $locale));
@endphp

@include('pdf._brand_band', [
    'bandTitle' => $company,
    'bandTag' => $decisionHeading,
    'bandMeta' => (! empty($snapshot['is_preview']) ? '<div class="tag" style="color:#c2410c;font-weight:700">PREVIEW / NOT ISSUED</div>' : '')
        .'<div class="tag" style="margin-top:6px">'.e(pdf_text(__('borrower.rejection_letter.pdf.application_reference', [], $locale))).': <strong>'.e($snapshot['application_number'] ?? '').'</strong></div>'
        .'<div class="tag">'.e(pdf_text(__('borrower.rejection_letter.pdf.decision_reference', [], $locale))).': <strong>'.e($agreement->reference).'</strong></div>'
        .'<div class="tag">'.e(pdf_text(__('borrower.rejection_letter.pdf.date', [], $locale))).': '.e(\Illuminate\Support\Carbon::parse($snapshot['rejected_at'] ?? now())->format('d M Y')).'</div>'
        .'<div class="tag">'.e(pdf_text(__('borrower.rejection_letter.pdf.product', [], $locale))).': '.e(pdf_text($snapshot['product_name'] ?? '')).'</div>',
])

@if (! empty($snapshot['is_preview']))
    <div class="preview-banner">
        <strong>PREVIEW / NOT ISSUED</strong><br>
        {{ pdf_text(__('borrower.rejection_letter.pdf.preview_notice', [], $locale)) }}
    </div>
@endif

<div class="wrap">
    <p><strong>{{ pdf_text(__('borrower.rejection_letter.pdf.greeting', ['name' => $snapshot['customer_name'] ?: __('borrower.rejection_letter.pdf.customer_fallback', [], $locale)], $locale)) }}</strong></p>
    <p>{{ pdf_text(__('borrower.rejection_letter.pdf.opening_thanks', [], $locale)) }}</p>
    <p>{{ pdf_text(__('borrower.rejection_letter.pdf.opening_decision', [], $locale)) }}</p>
    <p>{{ pdf_text(__('borrower.rejection_letter.pdf.opening_explain', [], $locale)) }}</p>

    @if ($isCapacity && empty($snapshot['is_group_rejection']))
        <h2>{{ pdf_text(__('borrower.rejection_letter.pdf.why_heading', [], $locale)) }}</h2>
        <p>{{ pdf_text(__('borrower.rejection_letter.pdf.capacity_intro', [], $locale)) }}</p>
        <p>{{ pdf_text(__('borrower.rejection_letter.pdf.capacity_ratio', [
            'ratio' => $ratioLabel,
            'income_basis' => $incomeBasisPhrase,
        ], $locale)) }}</p>
        <p>{{ pdf_text(__('borrower.rejection_letter.pdf.capacity_supported', [
            'income_basis' => $incomeBasisPhrase,
            'ratio' => $ratioLabel,
            'supported' => format_money($snapshot['supported_monthly_repayment'] ?? 0),
        ], $locale)) }}</p>
        <p>{{ pdf_text(__('borrower.rejection_letter.pdf.capacity_required', [
            'requested' => format_money($snapshot['requested_amount'] ?? 0),
            'proposed' => format_money($snapshot['proposed_monthly_repayment'] ?? 0),
        ], $locale)) }}</p>

        <table class="summary" width="100%" cellspacing="0" cellpadding="0">
            <tr><th colspan="2">{{ pdf_text(__('borrower.rejection_letter.pdf.summary_heading', [], $locale)) }}</th></tr>
            <tr>
                <td class="label">{{ pdf_text(__('borrower.rejection_letter.pdf.summary_requested', [], $locale)) }}</td>
                <td class="value">{{ format_money($snapshot['requested_amount'] ?? 0) }}</td>
            </tr>
            <tr>
                <td class="label">{{ pdf_text(__('borrower.rejection_letter.pdf.summary_proposed', [], $locale)) }}</td>
                <td class="value">{{ format_money($snapshot['proposed_monthly_repayment'] ?? 0) }}</td>
            </tr>
            <tr>
                <td class="label">{{ pdf_text(__('borrower.rejection_letter.pdf.summary_ratio', [], $locale)) }}</td>
                <td class="value">{{ $ratioLabel }}</td>
            </tr>
            <tr>
                <td class="label">{{ pdf_text(__('borrower.rejection_letter.pdf.summary_supported', [], $locale)) }}</td>
                <td class="value">{{ format_money($snapshot['supported_monthly_repayment'] ?? 0) }}</td>
            </tr>
        </table>
        @if ($reasonList !== [])
            <ul>
                @foreach ($reasonList as $reason)
                    <li>{{ pdf_text($reason) }}</li>
                @endforeach
            </ul>
        @endif
    @else
        <div class="notice" style="margin-top:14px">
            <strong>{{ pdf_text(__('borrower.rejection_letter.pdf.reason_heading', [], $locale)) }}</strong>
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
    @endif

    @if ($failed !== [])
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

    <div class="advice">
        <strong>{{ pdf_text(__('borrower.rejection_letter.pdf.advice_heading', [], $locale)) }}</strong><br>
        @if (! empty($snapshot['rejection_advice']))
            {{ pdf_text($snapshot['rejection_advice']) }}
        @elseif ($isCapacity)
            {{ pdf_text(__('borrower.rejection_letter.pdf.capacity_next_steps', [], $locale)) }}
        @else
            {{ pdf_text(__('borrower.rejection_letter.pdf.generic_next_steps', [], $locale)) }}
        @endif
    </div>

    <table class="keep-together" style="width:100%;margin-top:18px">
        <tr>
            <td style="width:58%;vertical-align:top">
                <strong>{{ pdf_text(__('borrower.rejection_letter.pdf.for_company', ['company' => $company], $locale)) }}</strong>
                @if (! empty($snapshot['company_signature_path']))
                    <div style="margin-top:4px"><img src="{{ $snapshot['company_signature_path'] }}" class="sig-img" alt=""></div>
                @else
                    <div style="height:28px"></div>
                @endif
                <div class="muted">{{ $snapshot['company_signatory_name'] ?? $company }}</div>
                @if (! empty($snapshot['company_signatory_title']))
                    <div class="muted">{{ $snapshot['company_signatory_title'] }}</div>
                @endif
            </td>
            <td style="width:42%;vertical-align:bottom;text-align:right">
                @if (! empty($snapshot['company_stamp_path']))
                    <div class="muted" style="margin-bottom:2px">{{ pdf_text(__('borrower.rejection_letter.pdf.company_stamp', [], $locale)) }}</div>
                    <img src="{{ $snapshot['company_stamp_path'] }}" class="stamp-img" alt="">
                @endif
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $company }}
        @if (filled($complaintsPhone)) · {{ $complaintsPhone }} @endif
        @if (filled($complaintsEmail)) · {{ $complaintsEmail }} @endif
        · {{ pdf_text(__('borrower.rejection_letter.pdf.decision_reference', [], $locale)) }} {{ $agreement->reference }}
    </div>
</div>
</body>
</html>
