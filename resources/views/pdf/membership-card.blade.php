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
            color: #ffffff;
            background: #047857;
            overflow: hidden;
        }
        .page {
            width: 105mm;
            height: 148mm;
            position: relative;
            overflow: hidden;
            page-break-inside: avoid;
            page-break-after: avoid;
            background: #059669;
        }
        .gold {
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            height: 2.2mm;
            background: #f5c842;
        }
        .inner {
            position: absolute;
            top: 7mm;
            left: 7mm;
            right: 7mm;
            bottom: 7mm;
        }
        .logo { height: 9mm; width: auto; }
        .badge {
            float: right;
            background: #ffffff;
            color: #065f46;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 1.2mm 3mm;
            border-radius: 999px;
        }
        .header { overflow: hidden; margin-bottom: 5mm; }
        .photo {
            float: left;
            width: 22mm;
            height: 28mm;
            border-radius: 4px;
            border: 1.5px solid rgba(255,255,255,0.45);
            object-fit: cover;
            background: rgba(255,255,255,0.15);
            margin-right: 3.5mm;
        }
        .photo-fallback {
            float: left;
            width: 22mm;
            height: 28mm;
            border-radius: 4px;
            border: 1.5px solid rgba(255,255,255,0.45);
            background: rgba(255,255,255,0.18);
            text-align: center;
            line-height: 28mm;
            font-size: 16pt;
            font-weight: bold;
            margin-right: 3.5mm;
        }
        .eyebrow {
            font-size: 5.5pt;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.72);
            font-weight: bold;
        }
        .member-name {
            margin-top: 1mm;
            font-size: 11pt;
            font-weight: bold;
            line-height: 1.2;
            text-transform: uppercase;
            max-height: 14mm;
            overflow: hidden;
        }
        .clear { clear: both; }
        .box {
            margin-top: 5mm;
            padding: 3mm 3.5mm;
            border-radius: 4px;
            background: rgba(0,0,0,0.18);
            border: 1px solid rgba(255,255,255,0.22);
        }
        .label {
            font-size: 5.5pt;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.65);
            font-weight: bold;
        }
        .member-no {
            margin-top: 1.5mm;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4mm;
        }
        .meta td {
            width: 50%;
            vertical-align: top;
            font-size: 8pt;
            padding-right: 2mm;
        }
        .meta .label { display: block; margin-bottom: 0.8mm; }
        .meta .value { font-weight: bold; font-size: 9pt; }
        .qr-wrap {
            margin-top: 5mm;
            padding: 3mm;
            border-radius: 4px;
            background: rgba(0,0,0,0.18);
            border: 1px solid rgba(255,255,255,0.22);
            overflow: hidden;
        }
        .qr {
            float: left;
            width: 18mm;
            height: 18mm;
            background: #ffffff;
            padding: 1mm;
            margin-right: 3mm;
        }
        .scan {
            font-size: 6.5pt;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            font-weight: bold;
            color: rgba(255,255,255,0.85);
            padding-top: 5mm;
        }
    </style>
</head>
<body>
    @php
        $name = strtoupper(trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')));
        if (mb_strlen($name) > 40) {
            $name = mb_substr($name, 0, 39).'…';
        }
        $initial = strtoupper(substr(trim($customer->first_name ?? ''), 0, 1) ?: '?');
        $status = strtoupper((string) $customer->membershipStatusLabel());
        $qrDataUri = $qrDataUri ?? null;
    @endphp
    <div class="page">
        <div class="gold"></div>
        <div class="inner">
            <div class="header">
                <span class="badge">{{ $status }}</span>
                @if (! empty($logoPath))
                    <img src="{{ $logoPath }}" alt="" class="logo">
                @else
                    <span style="font-size:12pt;font-weight:bold;">{{ brand_name() }}</span>
                @endif
            </div>

            @if (! empty($photoPath))
                <img src="{{ $photoPath }}" alt="" class="photo">
            @else
                <div class="photo-fallback">{{ $initial }}</div>
            @endif
            <p class="eyebrow">{{ brand_name() }} Member</p>
            <p class="member-name">{{ $name ?: '—' }}</p>
            <div class="clear"></div>

            <div class="box">
                <p class="label">Membership number</p>
                <p class="member-no">{{ \App\Support\MemberNumberFormatter::display($customer->member_no) }}</p>
            </div>

            <table class="meta">
                <tr>
                    <td>
                        <span class="label">Issued</span>
                        <span class="value">{{ optional($customer->membership_issued_at)->format('d M Y') ?? '—' }}</span>
                    </td>
                    <td>
                        <span class="label">Expires</span>
                        <span class="value">{{ optional($customer->membership_expires_at)->format('d M Y') ?? '—' }}</span>
                    </td>
                </tr>
            </table>

            @if (! empty($qrDataUri) || ! empty($verifyUrl))
                <div class="qr-wrap">
                    @if (! empty($qrDataUri))
                        <img src="{{ $qrDataUri }}" alt="" class="qr">
                    @endif
                    <p class="scan">Scan to verify</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
