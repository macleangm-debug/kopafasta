@php
    $receipt = $receipt ?? app(\App\Services\CustomerPaymentService::class)->receiptPayload($payment);
    // Borrower-facing PDF: Kopafasta payment reference only — never PSP/provider_ref.
    $rows = array_values(array_filter([
        ['label' => __('borrower.payments_page.show.type'), 'value' => $receipt['type']],
        ['label' => __('borrower.payments_page.show.payment_reference'), 'value' => $receipt['reference']],
        ['label' => __('borrower.payments_page.show.date'), 'value' => $receipt['paid_at_label']],
        ['label' => __('borrower.payments_page.show.status'), 'value' => $receipt['status']],
        filled($receipt['member_name'] ?? null)
            ? ['label' => __('borrower.payments_page.show.member_name'), 'value' => $receipt['member_name']]
            : null,
        filled($receipt['member_number'] ?? null)
            ? ['label' => __('borrower.payments_page.show.member_number'), 'value' => $receipt['member_number']]
            : null,
        filled($receipt['application_number'] ?? null)
            ? ['label' => __('borrower.payments_page.show.application_number'), 'value' => $receipt['application_number']]
            : null,
        filled($receipt['loan_number'] ?? null)
            ? ['label' => __('borrower.payments_page.show.loan_number'), 'value' => $receipt['loan_number']]
            : null,
        filled($receipt['phone_masked'] ?? null)
            ? ['label' => __('borrower.payments_page.show.mobile_number'), 'value' => $receipt['phone_masked']]
            : null,
    ]));
    if (is_array($receipt['allocation'] ?? null)) {
        $rows[] = ['label' => __('borrower.payments_page.show.amount_paid'), 'value' => format_money($receipt['allocation']['paid'])];
        $rows[] = ['label' => __('borrower.payments_page.show.amount_allocated'), 'value' => format_money($receipt['allocation']['allocated'])];
        $rows[] = ['label' => __('borrower.payments_page.show.remaining_due'), 'value' => format_money($receipt['allocation']['remaining'])];
    }
    $footerParts = array_values(array_filter([
        $receipt['legal_name'] ?? null,
        $receipt['support_phone'] ?? null,
        $receipt['support_email'] ?? null,
        $receipt['website'] ?? null,
    ]));
    $mark = function_exists('pdf_brand_mark_path') ? pdf_brand_mark_path() : null;
    $zigzag = 'data:image/svg+xml;base64,'.base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="10" viewBox="0 0 320 10" preserveAspectRatio="none">'
        .'<path fill="#ffffff" stroke="#e5e7eb" stroke-width="1" d="M0 10 L8 2 L16 10 L24 2 L32 10 L40 2 L48 10 L56 2 L64 10 L72 2 L80 10 L88 2 L96 10 L104 2 L112 10 L120 2 L128 10 L136 2 L144 10 L152 2 L160 10 L168 2 L176 10 L184 2 L192 10 L200 2 L208 10 L216 2 L224 10 L232 2 L240 10 L248 2 L256 10 L264 2 L272 10 L280 2 L288 10 L296 2 L304 10 L312 2 L320 10 Z"/>'
        .'</svg>'
    );
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $receipt['kicker'] }} {{ $receipt['reference'] }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; margin: 0; background: #f3f4f6; }
        .sheet { width: 100%; }
        .wrap { width: 320px; margin: 18mm auto 0; }
        .zigzag { width: 320px; height: 10px; display: block; }
        .zigzag-bottom { transform: rotate(180deg); }
        .card {
            width: 320px;
            border-left: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
            padding: 16px 20px 18px;
            background: #ffffff;
        }
        .top { width: 100%; }
        .top td { vertical-align: top; }
        .mark { height: 32px; }
        .kicker { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #6b7280; font-weight: bold; text-align: right; }
        .amount-label { font-size: 9px; letter-spacing: 1.4px; text-transform: uppercase; color: #6b7280; font-weight: bold; margin-top: 14px; }
        .amount { font-size: 24px; font-weight: bold; margin-top: 4px; }
        table.rows { width: 100%; margin-top: 12px; }
        table.rows td { padding: 6px 0; vertical-align: top; }
        .label { font-size: 8px; letter-spacing: 1.2px; text-transform: uppercase; color: #6b7280; font-weight: bold; }
        .value { font-size: 11px; font-weight: bold; margin-top: 3px; }
        .footer { font-size: 9px; color: #4b5563; margin-top: 14px; line-height: 1.45; }
        .keep { font-size: 10px; font-weight: bold; margin-top: 8px; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="wrap">
        <img class="zigzag" src="{{ $zigzag }}" alt="">
        <div class="card">
            <table class="top">
                <tr>
                    <td>
                        @if ($mark)
                            <img class="mark" src="{{ $mark }}" alt="{{ $receipt['brand'] }}">
                        @else
                            <div style="font-size:13px;font-weight:bold;">{{ $receipt['brand'] }}</div>
                        @endif
                    </td>
                    <td class="kicker">{{ $receipt['kicker'] }}</td>
                </tr>
            </table>
            <div class="amount-label">{{ __('borrower.payments_page.show.amount') }}</div>
            <div class="amount">{{ $receipt['amount'] }}</div>
            <table class="rows">
                @foreach ($rows as $row)
                    <tr>
                        <td>
                            <div class="label">{{ $row['label'] }}</div>
                            <div class="value">{{ $row['value'] }}</div>
                        </td>
                    </tr>
                @endforeach
            </table>
            @if ($footerParts !== [])
                <div class="footer">{{ implode(' · ', $footerParts) }}</div>
            @endif
            <div class="keep">{{ $receipt['keep_line'] }}</div>
        </div>
        <img class="zigzag zigzag-bottom" src="{{ $zigzag }}" alt="">
    </div>
</div>
</body>
</html>
