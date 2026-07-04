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
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            background: #ffffff;
        }
        .card {
            width: 105mm;
            height: 148mm;
            padding: 10mm;
            page-break-after: avoid;
            page-break-inside: avoid;
            background: linear-gradient(160deg, #ecfdf5 0%, #ffffff 42%, #fffbeb 100%);
            border: 2px solid #047857;
            border-radius: 14px;
            position: relative;
            overflow: hidden;
        }
        .accent {
            position: absolute;
            top: -18mm;
            right: -18mm;
            width: 48mm;
            height: 48mm;
            border-radius: 999px;
            background: rgba(245, 200, 66, 0.22);
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 8mm;
        }
        .logo-cell, .title-cell {
            display: table-cell;
            vertical-align: middle;
        }
        .logo-cell { width: 28mm; }
        .logo {
            width: 24mm;
            height: auto;
        }
        .brand-name {
            font-size: 13pt;
            font-weight: bold;
            color: #004d40;
            letter-spacing: 0.5px;
        }
        .brand-tag {
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #64748b;
            margin-top: 1mm;
        }
        .member-row {
            display: table;
            width: 100%;
            margin-bottom: 6mm;
        }
        .photo-wrap, .member-info {
            display: table-cell;
            vertical-align: top;
        }
        .photo-wrap { width: 30mm; padding-right: 5mm; }
        .photo, .photo-fallback {
            width: 28mm;
            height: 34mm;
            border-radius: 8px;
            border: 2px solid #d97706;
            object-fit: cover;
            background: #e2e8f0;
        }
        .photo-fallback {
            text-align: center;
            line-height: 34mm;
            font-size: 22pt;
            font-weight: bold;
            color: #ffffff;
            background: #047857;
        }
        .member-name {
            font-size: 16pt;
            font-weight: bold;
            color: #0f172a;
            line-height: 1.15;
            text-transform: uppercase;
        }
        .status-badge {
            display: inline-block;
            margin-top: 2mm;
            padding: 1.2mm 3mm;
            border-radius: 999px;
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .member-no {
            margin-top: 4mm;
            padding: 3mm 4mm;
            border-radius: 8px;
            background: rgba(4, 120, 87, 0.08);
            border: 1px solid #a7f3d0;
        }
        .member-no-label {
            font-size: 6pt;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #64748b;
        }
        .member-no-value {
            margin-top: 1mm;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 12pt;
            font-weight: bold;
            color: #047857;
            letter-spacing: 1px;
        }
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4mm;
        }
        .meta-grid td {
            font-size: 8pt;
            padding: 1.5mm 0;
            vertical-align: top;
        }
        .meta-grid td.label {
            width: 34%;
            color: #64748b;
        }
        .meta-grid td.value {
            font-weight: bold;
            color: #0f172a;
        }
        .footer {
            position: absolute;
            left: 10mm;
            right: 10mm;
            bottom: 10mm;
            border-top: 1px dashed #cbd5e1;
            padding-top: 3mm;
        }
        .footer-title {
            font-size: 7pt;
            font-weight: bold;
            color: #047857;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .footer-text {
            margin-top: 1.5mm;
            font-size: 6.5pt;
            color: #475569;
            line-height: 1.35;
        }
        .verify {
            margin-top: 2mm;
            font-size: 5.5pt;
            color: #64748b;
            word-break: break-all;
        }
    </style>
</head>
<body>
    @php
        $name = strtoupper(trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')));
        $initial = strtoupper(substr(trim($customer->first_name ?? ''), 0, 1) ?: '?');
    @endphp
    <div class="card">
        <div class="accent"></div>

        <div class="header">
            <div class="logo-cell">
                @if (! empty($logoPath))
                    <img src="{{ $logoPath }}" alt="" class="logo">
                @endif
            </div>
            <div class="title-cell">
                <p class="brand-name">{{ brand_name() }}</p>
                <p class="brand-tag">Official membership card</p>
            </div>
        </div>

        <div class="member-row">
            <div class="photo-wrap">
                @if (! empty($photoPath))
                    <img src="{{ $photoPath }}" alt="" class="photo">
                @else
                    <div class="photo-fallback">{{ $initial }}</div>
                @endif
            </div>
            <div class="member-info">
                <p class="member-name">{{ $name ?: '—' }}</p>
                <span class="status-badge">{{ $customer->membershipStatusLabel() }}</span>
                <div class="member-no">
                    <p class="member-no-label">Membership number</p>
                    <p class="member-no-value">{{ \App\Support\MemberNumberFormatter::display($customer->member_no) }}</p>
                </div>
                <table class="meta-grid">
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
        </div>

        <div class="footer">
            <p class="footer-title">{{ __('borrower.membership.benefits_title') }}</p>
            <p class="footer-text">{{ collect(__('borrower.membership.benefits'))->take(2)->implode(' · ') }}</p>
            @if (! empty($verifyUrl))
                <p class="verify">{{ $verifyUrl }}</p>
            @endif
        </div>
    </div>
</body>
</html>
