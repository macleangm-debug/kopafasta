<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $snapshot['locale'] ?? app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>{{ pdf_text(__('borrower.offer_letter.pdf.title', ['reference' => $agreement->reference], $snapshot['locale'] ?? app()->getLocale())) }}</title>
@include('pdf.loan-agreement._styles')
</head>
<body>
@php require resource_path('views/pdf/loan-agreement/locale.php'); @endphp
@php
    $validityDays = (int) ($snapshot['offer_validity_days'] ?? 14);
    $expiresAt = $agreement->expires_at?->toDateString()
        ?? $snapshot['offer_expires_at']
        ?? now()->addDays($validityDays)->toDateString();
    $cadences = __('borrower.agreement.repayment_cadences', [], $locale);
    $cadenceKey = $snapshot['repayment_cadence'] ?? 'weekly';
    $customerName = $snapshot['customer_name'] ?: __('borrower.offer_letter.pdf.customer_fallback', [], $locale);
    $clauses = $snapshot['legal_clauses'] ?? [];
    $jurisdiction = $jurisdictionLabel($snapshot['jurisdiction'] ?? ($clauses['jurisdiction'] ?? null));
    $graceDays = (int) ($snapshot['grace_days'] ?? $clauses['grace_days'] ?? 0);
    $penaltyCap = format_number($snapshot['penalty_cap_percent'] ?? $clauses['penalty_cap_percent'] ?? 0, 0);
    $recovery = $snapshot['recovery_schedule'] ?? [];
    $stages = $recovery['stages'] ?? [];
    $facilityCharges = $snapshot['facility_charges'] ?? [];
    $showGpsFee = ! empty($snapshot['gps_fee']);
    $showCharges = isset($snapshot['grace_days'])
        || isset($snapshot['penalty_rate'])
        || $stages !== []
        || $facilityCharges !== []
        || $showGpsFee;
    $approvalLabel = trim((string) ($snapshot['approval_reason_label'] ?? ''));
    $approvalNotes = trim((string) ($snapshot['approval_reason_notes'] ?? ''));
    $tagline = filled($snapshot['jurisdiction'] ?? null) || filled($clauses['jurisdiction'] ?? null)
        ? ($isSw ? 'Huduma za mikopo ndogo — ' : 'Microfinance Services — ').$jurisdiction
        : pdf_text(__('borrower.offer_letter.pdf.tagline', [], $locale));
@endphp

@include('pdf._brand_band', [
    'bandTitle' => $snapshot['company_legal_name'] ?? brand('legal_name'),
    'bandTag' => $tagline,
    'bandMeta' => '<div class="pill">'.e(pdf_text(__('borrower.offer_letter.pdf.pill', [], $locale))).'</div>'
        .'<div class="tag" style="margin-top:8px">'.e(pdf_text(__('borrower.offer_letter.pdf.reference', [], $locale))).': <strong>'.e($agreement->reference).'</strong></div>'
        .'<div class="tag">'.e(pdf_text(__('borrower.offer_letter.pdf.date', [], $locale))).': '.e(now()->format('d M Y')).'</div>',
])

<div class="wrap">
    <p>{{ pdf_text(__('borrower.offer_letter.pdf.greeting', ['name' => $customerName], $locale)) }}</p>
    <p>{{ pdf_text(__('borrower.offer_letter.pdf.intro', [], $locale)) }}</p>

    @include('pdf.loan-agreement._parties')

    <h2>{{ pdf_text(__('borrower.offer_letter.pdf.facility_heading', [], $locale)) }}</h2>
    <table class="kv">
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.application_number', [], $locale)) }}</td><td class="value">{{ $snapshot['application_number'] }}</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.product', [], $locale)) }}</td><td class="value">{{ pdf_text($snapshot['product_name']) }} ({{ $snapshot['product_code'] }})</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.approved_amount', [], $locale)) }}</td><td class="value">{{ format_money($snapshot['principal']) }}</td></tr>
        <tr><td class="label">{{ pdf_text(! empty($snapshot['hides_interest']) ? __('borrower.offer_letter.pdf.charge_rate', [], $locale) : __('borrower.offer_letter.pdf.interest_rate', [], $locale)) }}</td><td class="value">{{ pdf_text(! empty($snapshot['hides_interest']) ? __('borrower.offer_letter.pdf.charge_rate_value', ['rate' => format_number(($snapshot['displayed_monthly_rate'] ?? $snapshot['interest_rate'] ?? 0) * 100, 2)], $locale) : __('borrower.offer_letter.pdf.interest_rate_value', ['rate' => format_number(($snapshot['displayed_monthly_rate'] ?? $snapshot['interest_rate'] ?? 0) * 100, 2)], $locale)) }}</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.tenure', [], $locale)) }}</td><td class="value">{{ pdf_text(__('borrower.offer_letter.pdf.tenure_months', ['months' => $snapshot['tenure_months']], $locale)) }}</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.repayment_frequency', [], $locale)) }}</td><td class="value">{{ pdf_text($cadences[$cadenceKey] ?? ucfirst($cadenceKey)) }}</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.installment_count', [], $locale)) }}</td><td class="value">{{ $snapshot['installment_count'] ?? count($snapshot['repayment_schedule'] ?? []) }}</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.installment_amount', [], $locale)) }}</td><td class="value">{{ format_money($snapshot['estimated_emi']) }}</td></tr>
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.total_repayable', [], $locale)) }}</td><td class="value">{{ format_money($snapshot['total_repayable'] ?? 0) }}</td></tr>
        @if (filled($snapshot['purpose'] ?? null))
            <tr><td class="label">{{ $t('Purpose', 'Madhumuni') }}</td><td class="value">{{ pdf_text($purposeLabel($snapshot['purpose'] ?? null, $snapshot['purpose_other'] ?? null)) }}</td></tr>
        @endif
        @if ($approvalLabel !== '')
            <tr><td class="label">{{ $t('Approval reason', 'Sababu ya idhini') }}</td><td class="value">{{ pdf_text($approvalLabel) }}</td></tr>
        @endif
        @if ($approvalNotes !== '' && strcasecmp($approvalNotes, $approvalLabel) !== 0)
            <tr><td class="label">{{ $t('Committee notes', 'Maelezo ya kamati') }}</td><td class="value">{{ pdf_text($approvalNotes) }}</td></tr>
        @endif
        <tr><td class="label">{{ pdf_text(__('borrower.offer_letter.pdf.offer_expires', [], $locale)) }}</td><td class="value">{{ pdf_text(__('borrower.offer_letter.pdf.offer_expires_value', ['date' => \Illuminate\Support\Carbon::parse($expiresAt)->format('d M Y'), 'days' => $validityDays], $locale)) }}</td></tr>
    </table>

    @if ($showCharges)
        <h2>{{ $t('Charges that apply if you accept', 'Ada zinazotumika ukikubali') }}</h2>
        <table class="charges">
            @if (isset($snapshot['grace_days']) || $graceDays > 0)
                <tr><td>{{ $t('Grace period', 'Muda wa msamaha') }}</td><td>{{ $graceDays }} {{ $t('calendar days', 'siku za kalenda') }}</td></tr>
            @endif
            @if (isset($snapshot['penalty_rate']))
                <tr><td>{{ $t('Penalty', 'Adhabu') }}</td><td>{{ format_number($snapshot['penalty_rate'] ?? 0, 2) }}% {{ $isSw ? ($snapshot['penalty_basis_label_sw'] ?? '') : ($snapshot['penalty_basis_label'] ?? 'per day') }} {{ $t('on the first overdue instalment remainder', 'kwenye salio la awamu ya kwanza iliyochelewa') }}</td></tr>
                <tr><td>{{ $t('Penalty cap', 'Kizuizi cha adhabu') }}</td><td>{{ $penaltyCap }}% {{ $t('of all overdue instalment remainders', 'ya salio zote za awamu zilizochelewa') }}</td></tr>
            @endif
            @foreach ($stages as $stage)
                <tr><td>{{ $isSw ? ($stage['label_sw'] ?? $stage['label'] ?? '') : ($stage['label_en'] ?? $stage['label'] ?? '') }}</td><td>{{ $isSw ? ($stage['display_sw'] ?? '') : ($stage['display_en'] ?? '') }}</td></tr>
            @endforeach
            @foreach ($facilityCharges as $charge)
                <tr><td>{{ $charge['name'] ?? $charge['code'] ?? '' }}</td><td>{{ $isSw ? ($charge['display_sw'] ?? '') : ($charge['display_en'] ?? '') }}</td></tr>
            @endforeach
            @if ($showGpsFee)
                <tr>
                    <td>{{ $t('GPS (post-approval)', 'GPS (baada ya kuidhinishwa)') }}</td>
                    <td>{{ format_money($snapshot['gps_fee']['total'] ?? 0) }} · {{ $t('install + monthly × tenure', 'usakinishaji + kila mwezi × muda') }}</td>
                </tr>
                <tr><td>{{ $t('GPS installation', 'Usakinishaji wa GPS') }}</td><td>{{ format_money($snapshot['gps_fee']['install_amount'] ?? 0) }}</td></tr>
                <tr><td>{{ $t('GPS monthly monitoring', 'Ufuatiliaji wa GPS kila mwezi') }}</td><td>{{ format_money($snapshot['gps_fee']['monthly_amount'] ?? 0) }} × {{ (int) ($snapshot['gps_fee']['months'] ?? 0) }} {{ $t('months', 'miezi') }}</td></tr>
            @endif
        </table>
        <p class="muted">{{ $t('A recovery charge becomes payable only when the relevant recovery stage is actually initiated and the charge is posted. These figures are taken from Settings at the time this offer was generated.', 'Gharama ya urejeshaji inadaiwa pale tu hatua husika inapoanzishwa na gharama inarekodiwa. Takwimu hizi zinatokana na Mipangilio wakati ofa hii ilipotengenezwa.') }}</p>
    @endif

    @if (! empty($snapshot['is_asset_loan']) && (filled($snapshot['asset_title'] ?? null) || filled($snapshot['collateral_description'] ?? null) || filled($snapshot['collateral_market_value'] ?? null)))
        <h2>{{ $t('Collateral', 'Dhamana') }}</h2>
        <table class="kv">
            <tr><td class="label">{{ $t('Asset', 'Mali') }}</td><td class="value">{{ $snapshot['asset_title'] ?? $snapshot['collateral_description'] ?? '—' }}</td></tr>
            @if (filled($snapshot['collateral_market_value'] ?? null))
                <tr><td class="label">{{ $t('Value', 'Thamani') }}</td><td class="value">{{ format_money($snapshot['collateral_market_value']) }}</td></tr>
            @endif
        </table>
    @endif

    <div class="notice">
        <strong>{{ $t('Acceptance of commercial terms', 'Kukubali masharti ya kibiashara') }}</strong>
        @if ($isSw)
            <p>Kwa kusaini Barua hii ya Ofa, Mkopaji na, pale inapohusika, Mdhamini na Wanachama wa Kikundi wanathibitisha wamesoma na kukubali kiasi, riba, ratiba, ada, masharti ya ukiukaji na wajibu, kulingana na kutia saini Mkataba wa Mkopo na Masharti ya Huduma ya Mkopo.</p>
        @else
            <p>By signing this Offer Letter, the Borrower and, where applicable, the Guarantor and Group Members acknowledge that they have reviewed and accepted the principal amount, interest, repayment schedule, applicable charges, default provisions and the obligations described herein, subject to execution of the Loan Agreement and Facility Terms.</p>
        @endif
        <p><strong>{{ pdf_text(__('borrower.offer_letter.pdf.next_steps_heading', [], $locale)) }}</strong> {{ pdf_text(__('borrower.offer_letter.pdf.next_steps_body', [], $locale)) }}</p>
    </div>

    @include('pdf.loan-agreement._signatories')

    <div class="footer">
        {{ pdf_text(__('borrower.offer_letter.pdf.footer', ['company' => $snapshot['company_legal_name'] ?? brand('legal_name'), 'email' => $snapshot['complaints_email'] ?? brand('support_email')], $locale)) }}
    </div>
</div>
</body>
</html>
