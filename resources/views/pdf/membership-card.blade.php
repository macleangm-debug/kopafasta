<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ brand_name() }} Membership</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111; margin: 0; padding: 16px; }
        .card { border: 3px solid #047857; border-radius: 16px; overflow: hidden; background: linear-gradient(135deg, #ecfdf5 0%, #fffbeb 100%); }
        .header { background: #047857; color: #fff; padding: 16px 20px; }
        .brand { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; opacity: 0.85; }
        .body { padding: 20px; }
        .row { display: table; width: 100%; }
        .photo-cell, .info-cell { display: table-cell; vertical-align: top; }
        .photo-cell { width: 88px; padding-right: 16px; }
        .photo { width: 80px; height: 96px; border-radius: 8px; border: 2px solid #d97706; object-fit: cover; background: #e5e7eb; }
        .photo-placeholder { width: 80px; height: 96px; border-radius: 8px; border: 2px solid #d97706; background: #047857; color: #fff; text-align: center; line-height: 96px; font-size: 28px; font-weight: bold; }
        h1 { margin: 4px 0 0; font-size: 20px; letter-spacing: 0.5px; }
        .badge { display: inline-block; margin-top: 6px; padding: 3px 10px; border-radius: 999px; background: #ecfdf5; color: #065f46; font-size: 10px; font-weight: bold; border: 1px solid #a7f3d0; }
        .number-box { margin-top: 14px; padding: 12px 14px; border-radius: 10px; background: rgba(4, 120, 87, 0.08); border: 1px solid #a7f3d0; }
        .number-label { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #6b7280; }
        .number { margin-top: 4px; font-family: DejaVu Sans Mono, monospace; font-size: 18px; letter-spacing: 2px; font-weight: bold; color: #047857; }
        dl.meta { margin-top: 14px; width: 100%; }
        dl.meta dt, dl.meta dd { display: inline-block; font-size: 11px; padding: 2px 0; }
        dl.meta dt { width: 38%; color: #6b7280; }
        dl.meta dd { width: 58%; font-weight: bold; }
        .benefits { margin-top: 16px; padding-top: 14px; border-top: 1px dashed #d1d5db; }
        .benefits h2 { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #047857; margin: 0 0 8px; }
        .benefits li { font-size: 10px; margin-bottom: 4px; color: #374151; list-style: none; }
        .benefits li:before { content: "✓ "; color: #047857; font-weight: bold; }
        .verify { margin-top: 14px; font-size: 9px; color: #374151; word-break: break-all; padding: 8px 10px; background: #f9fafb; border-radius: 8px; }
    </style>
</head>
<body>
    @php
        $name = strtoupper(trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')));
        $initial = strtoupper(substr(trim($customer->first_name ?? ''), 0, 1) ?: '?');
    @endphp
    <div class="card">
        <div class="header">
            <p class="brand">{{ brand_name() }} · Official Member Card</p>
        </div>
        <div class="body">
            <div class="row">
                <div class="photo-cell">
                    @if (! empty($photoPath))
                        <img src="{{ $photoPath }}" alt="" class="photo">
                    @else
                        <div class="photo-placeholder">{{ $initial }}</div>
                    @endif
                </div>
                <div class="info-cell">
                    <h1>{{ $name ?: '—' }}</h1>
                    <span class="badge">{{ $customer->membershipStatusLabel() }}</span>
                    <div class="number-box">
                        <p class="number-label">Membership number</p>
                        <p class="number">{{ \App\Support\MemberNumberFormatter::display($customer->member_no) }}</p>
                    </div>
                    <dl class="meta">
                        <dt>Issued</dt><dd>{{ optional($customer->membership_issued_at)->format('d M Y') ?? '—' }}</dd><br>
                        <dt>Expires</dt><dd>{{ optional($customer->membership_expires_at)->format('d M Y') ?? '—' }}</dd><br>
                        <dt>Days remaining</dt><dd>{{ max(0, (int) $customer->membershipDaysRemaining()) }}</dd>
                    </dl>
                </div>
            </div>

            <div class="benefits">
                <h2>{{ __('borrower.membership.benefits_title') }}</h2>
                <ul>
                    @foreach (__('borrower.membership.benefits') as $benefit)
                        <li>{{ $benefit }}</li>
                    @endforeach
                </ul>
            </div>

            @if (! empty($verifyUrl))
                <p class="verify">{{ $verifyUrl }}</p>
            @endif
        </div>
    </div>
</body>
</html>
