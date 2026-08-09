<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ brand_name() }} Membership</title>
    <style>
        @page { margin: 0; size: 105mm 148mm portrait; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            width: 105mm;
            height: 148mm;
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            background: #ffffff;
            overflow: hidden;
        }
        .page {
            width: 105mm;
            height: 148mm;
            position: relative;
            overflow: hidden;
            page-break-inside: avoid;
            page-break-after: avoid;
            background: #ecfdf5;
            border: 2px solid #047857;
        }
        .inner {
            position: absolute;
            top: 8mm;
            left: 8mm;
            right: 8mm;
            bottom: 8mm;
            overflow: hidden;
        }
        .header {
            width: 100%;
            overflow: hidden;
            margin-bottom: 5mm;
        }
        .logo {
            float: left;
            width: 18mm;
            height: auto;
            margin-right: 3mm;
        }
        .brand-name {
            font-size: 11pt;
            font-weight: bold;
            color: #004d40;
        }
        .brand-tag {
            font-size: 6pt;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #64748b;
            margin-top: 1mm;
        }
        .photo {
            float: left;
            width: 24mm;
            height: 30mm;
            border-radius: 6px;
            border: 1.5px solid #d97706;
            object-fit: cover;
            background: #e2e8f0;
            margin-right: 4mm;
        }
        .photo-fallback {
            float: left;
            width: 24mm;
            height: 30mm;
            border-radius: 6px;
            border: 1.5px solid #d97706;
            background: #047857;
            color: #ffffff;
            text-align: center;
            line-height: 30mm;
            font-size: 18pt;
            font-weight: bold;
            margin-right: 4mm;
        }
        .member-name {
            font-size: 12pt;
            font-weight: bold;
            color: #0f172a;
            line-height: 1.2;
            text-transform: uppercase;
            max-height: 15mm;
            overflow: hidden;
        }
        .status-badge {
            display: inline-block;
            margin-top: 1.5mm;
            padding: 1mm 2.5mm;
            border-radius: 999px;
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .member-no {
            clear: both;
            margin-top: 5mm;
            padding: 2.5mm 3mm;
            border-radius: 6px;
            background: rgba(4, 120, 87, 0.08);
            border: 1px solid #a7f3d0;
        }
        .member-no-label {
            font-size: 5.5pt;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #64748b;
        }
        .member-no-value {
            margin-top: 1mm;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 11pt;
            font-weight: bold;
            color: #047857;
            letter-spacing: 0.5px;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4mm;
        }
        .meta td {
            font-size: 7.5pt;
            padding: 1.2mm 0;
            vertical-align: top;
        }
        .meta td.label { width: 32%; color: #64748b; }
        .meta td.value { font-weight: bold; color: #0f172a; }
        .footer {
            position: absolute;
            left: 8mm;
            right: 8mm;
            bottom: 8mm;
            border-top: 1px dashed #cbd5e1;
            padding-top: 2.5mm;
        }
        .footer-title {
            font-size: 6.5pt;
            font-weight: bold;
            color: #047857;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .footer-text {
            margin-top: 1mm;
            font-size: 6pt;
            color: #475569;
            line-height: 1.3;
            max-height: 8mm;
            overflow: hidden;
        }
        .verify {
            margin-top: 1.5mm;
            font-size: 5pt;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    @php
        $name = strtoupper(trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')));
        if (mb_strlen($name) > 36) {
            $name = mb_substr($name, 0, 35).'…';
        }
        $initial = strtoupper(substr(trim($customer->first_name ?? ''), 0, 1) ?: '?');
        $benefit = collect(__('borrower.membership.benefits'))->take(1)->first() ?? '';
        $verifyShort = $verifyUrl ? preg_replace('#^https?://#', '', (string) $verifyUrl) : null;
        if ($verifyShort && strlen($verifyShort) > 58) {
            $verifyShort = substr($verifyShort, 0, 57).'…';
        }
    @endphp
    <div class="page">
        <div class="inner">
            <div class="header">
                @if (! empty($logoPath))
                    <img src="{{ $logoPath }}" alt="" class="logo">
                @endif
                <p class="brand-name">{{ brand_name() }}</p>
                <p class="brand-tag">Official membership card</p>
            </div>

            @if (! empty($photoPath))
                <img src="{{ $photoPath }}" alt="" class="photo">
            @else
                <div class="photo-fallback">{{ $initial }}</div>
            @endif
            <p class="member-name">{{ $name ?: '—' }}</p>
            <span class="status-badge">{{ $customer->membershipStatusLabel() }}</span>

            <div class="member-no">
                <p class="member-no-label">Membership number</p>
                <p class="member-no-value">{{ \App\Support\MemberNumberFormatter::display($customer->member_no) }}</p>
            </div>

            <table class="meta">
                <tr>
                    <td class="label">Issued</td>
                    <td class="value">{{ optional($customer->membership_issued_at)->format('d M Y') ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Expires</td>
                    <td class="value">{{ optional($customer->membership_expires_at)->format('d M Y') ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Valid for</td>
                    <td class="value">{{ max(0, (int) $customer->membershipDaysRemaining()) }} days</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <p class="footer-title">{{ __('borrower.membership.benefits_title') }}</p>
            @if ($benefit !== '')
                <p class="footer-text">{{ $benefit }}</p>
            @endif
            @if (! empty($verifyShort))
                <p class="verify">{{ $verifyShort }}</p>
            @endif
        </div>
    </div>
</body>
</html>
