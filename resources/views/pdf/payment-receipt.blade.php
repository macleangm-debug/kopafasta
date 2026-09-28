@php
    $receipt = $receipt ?? app(\App\Services\CustomerPaymentService::class)->receiptPayload($payment);
    $rows = array_values(array_filter([
        ['label' => __('borrower.payments_page.show.type'), 'value' => $receipt['type']],
        ['label' => __('borrower.payments_page.show.payment_reference'), 'value' => $receipt['reference']],
        filled($receipt['provider_ref'] ?? null)
            ? ['label' => __('borrower.payments_page.show.provider_reference'), 'value' => $receipt['provider_ref']]
            : null,
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
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $receipt['kicker'] }} {{ $receipt['reference'] }}</title>
    <style>
        @page { size: A4; margin: 18mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; margin: 0; }
        .sheet { width: 100%; height: 100%; }
        .card {
            width: 420px;
            margin: 28mm auto 0;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 22px 24px;
            background: #ffffff;
        }
        .top { width: 100%; }
        .top td { vertical-align: top; }
        .mark { height: 36px; }
        .kicker { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #6b7280; font-weight: bold; text-align: right; }
        .amount-label { font-size: 9px; letter-spacing: 1.4px; text-transform: uppercase; color: #6b7280; font-weight: bold; margin-top: 18px; }
        .amount { font-size: 26px; font-weight: bold; margin-top: 4px; }
        table.rows { width: 100%; margin-top: 14px; }
        table.rows td { width: 50%; padding: 7px 8px 7px 0; vertical-align: top; }
        .label { font-size: 8px; letter-spacing: 1.2px; text-transform: uppercase; color: #6b7280; font-weight: bold; }
        .value { font-size: 11px; font-weight: bold; margin-top: 3px; }
        .footer { font-size: 9px; color: #4b5563; margin-top: 16px; line-height: 1.45; }
        .keep { font-size: 10px; font-weight: bold; margin-top: 8px; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="card">
        <table class="top">
            <tr>
                <td>
                    @if ($mark)
                        <img class="mark" src="{{ $mark }}" alt="">
                    @endif
                    <div style="font-size:13px;font-weight:bold;margin-top:6px;">{{ $receipt['brand'] }}</div>
                </td>
                <td class="kicker">{{ $receipt['kicker'] }}</td>
            </tr>
        </table>
        <div class="amount-label">{{ __('borrower.payments_page.show.amount') }}</div>
        <div class="amount">{{ $receipt['amount'] }}</div>
        <table class="rows">
            @foreach (array_chunk($rows, 2) as $pair)
                <tr>
                    @foreach ($pair as $row)
                        <td>
                            <div class="label">{{ $row['label'] }}</div>
                            <div class="value">{{ $row['value'] }}</div>
                        </td>
                    @endforeach
                    @if (count($pair) === 1)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </table>
        @if ($footerParts !== [])
            <div class="footer">{{ implode(' · ', $footerParts) }}</div>
        @endif
        <div class="keep">{{ $receipt['keep_line'] }}</div>
    </div>
</div>
</body>
</html>
