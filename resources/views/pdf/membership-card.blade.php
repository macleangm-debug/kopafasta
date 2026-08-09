<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
            color: #ffffff;
            background: #0B3D32;
            overflow: hidden;
        }
        .page {
            width: 105mm;
            height: 148mm;
            position: relative;
            overflow: hidden;
            page-break-inside: avoid;
            background: #0B3D32;
        }
        .gold {
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            height: 2.4mm;
            background: #f5c842;
        }
        .inner {
            position: absolute;
            top: 7mm;
            left: 7mm;
            right: 7mm;
            bottom: 7mm;
        }
        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6mm;
        }
        .header td { vertical-align: middle; }
        .brand-cell { width: 68%; }
        .logo {
            height: 7mm;
            width: auto;
            vertical-align: middle;
            margin-right: 2mm;
        }
        .wordmark {
            display: inline-block;
            vertical-align: middle;
            font-size: 13pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: -0.4px;
            line-height: 1;
        }
        .badge {
            display: inline-block;
            background: #ffffff;
            color: #0B3D32;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 1.4mm 3.2mm;
            border-radius: 999px;
        }
        .profile {
            width: 100%;
            border-collapse: collapse;
        }
        .profile td { vertical-align: top; }
        .photo {
            width: 22mm;
            height: 28mm;
            border-radius: 4px;
            border: 1.6px solid #f5c842;
            object-fit: cover;
            background: rgba(255,255,255,0.12);
        }
        .photo-fallback {
            width: 22mm;
            height: 28mm;
            border-radius: 4px;
            border: 1.6px solid #f5c842;
            background: rgba(255,255,255,0.12);
            text-align: center;
            line-height: 28mm;
            font-size: 16pt;
            font-weight: bold;
        }
        .profile-copy { padding-left: 3.5mm; padding-top: 1mm; }
        .eyebrow {
            font-size: 6pt;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            color: #f5c842;
            font-weight: bold;
            line-height: 1;
            margin: 0;
        }
        .member-name {
            margin-top: 1.2mm;
            font-size: 11.5pt;
            font-weight: bold;
            line-height: 1.05;
            text-transform: uppercase;
            max-height: 13mm;
            overflow: hidden;
        }
        .box {
            margin-top: 5.5mm;
            padding: 3.2mm 3.6mm;
            border-radius: 4px;
            background: rgba(0,0,0,0.28);
            border: 1px solid rgba(255,255,255,0.16);
        }
        .label {
            font-size: 5.5pt;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.55);
            font-weight: bold;
        }
        .member-no {
            margin-top: 1.4mm;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 12.5pt;
            font-weight: bold;
            letter-spacing: 1px;
            line-height: 1.15;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4.5mm;
        }
        .meta td {
            width: 50%;
            vertical-align: top;
            font-size: 8pt;
            padding-right: 2mm;
        }
        .meta .label { display: block; margin-bottom: 0.8mm; color: rgba(255,255,255,0.55); }
        .meta .value { font-weight: bold; font-size: 9pt; line-height: 1.15; }
        .qr-wrap {
            margin-top: 5mm;
            padding: 3mm;
            border-radius: 4px;
            background: rgba(0,0,0,0.28);
            border: 1px solid rgba(255,255,255,0.16);
            overflow: hidden;
        }
        .qr {
            float: left;
            width: 18mm;
            height: 18mm;
            background: #ffffff;
            padding: 1.2mm;
            margin-right: 3mm;
            border-radius: 2px;
        }
        .scan {
            font-size: 6.5pt;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            font-weight: bold;
            color: #f5c842;
            padding-top: 5mm;
        }
    </style>
</head>
<body>
    @php
        $name = strtoupper(trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')));
        if (mb_strlen($name) > 42) {
            $name = mb_substr($name, 0, 41).'…';
        }
        $initial = strtoupper(substr(trim($customer->first_name ?? ''), 0, 1) ?: '?');
        $status = strtoupper((string) $customer->membershipStatusLabel());
        $qrDataUri = $qrDataUri ?? null;
        $memberLabel = __('borrower.membership.member_role');
    @endphp
    <div class="page">
        <div class="gold"></div>
        <div class="inner">
            <table class="header">
                <tr>
                    <td class="brand-cell">
                        @if (! empty($logoPath))
                            <img src="{{ $logoPath }}" alt="" class="logo">
                        @endif
                        <span class="wordmark">{{ brand_name() }}</span>
                    </td>
                    <td style="text-align:right;">
                        <span class="badge">{{ $status }}</span>
                    </td>
                </tr>
            </table>

            <table class="profile">
                <tr>
                    <td style="width:22mm;">
                        @if (! empty($photoPath))
                            <img src="{{ $photoPath }}" alt="" class="photo">
                        @else
                            <div class="photo-fallback">{{ $initial }}</div>
                        @endif
                    </td>
                    <td class="profile-copy">
                        <p class="eyebrow">{{ $memberLabel }}</p>
                        <p class="member-name">{{ $name ?: '—' }}</p>
                    </td>
                </tr>
            </table>

            <div class="box">
                <p class="label">{{ __('borrower.membership.member_no_label') }}</p>
                <p class="member-no">{{ \App\Support\MemberNumberFormatter::display($customer->member_no) }}</p>
            </div>

            <table class="meta">
                <tr>
                    <td>
                        <span class="label">{{ __('borrower.membership.issued_label') }}</span>
                        <span class="value">{{ optional($customer->membership_issued_at)->format('d M Y') ?? '—' }}</span>
                    </td>
                    <td>
                        <span class="label">{{ __('borrower.membership.expires_label') }}</span>
                        <span class="value">{{ optional($customer->membership_expires_at)->format('d M Y') ?? '—' }}</span>
                    </td>
                </tr>
            </table>

            @if (! empty($qrDataUri) || ! empty($verifyUrl))
                <div class="qr-wrap">
                    @if (! empty($qrDataUri))
                        <img src="{{ $qrDataUri }}" alt="" class="qr">
                    @endif
                    <p class="scan">{{ __('borrower.membership.scan_to_verify') }}</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
