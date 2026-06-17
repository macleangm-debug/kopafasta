<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ brand_name() }} Membership</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111; margin: 24px; }
        .card { border: 2px solid #d97706; border-radius: 12px; padding: 24px; background: #fffbeb; }
        .brand { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #92400e; }
        h1 { margin: 8px 0 0; font-size: 22px; }
        .badge { display: inline-block; margin-top: 8px; padding: 4px 10px; border-radius: 999px; background: #ecfdf5; color: #065f46; font-size: 11px; font-weight: bold; }
        .number { margin-top: 20px; font-family: DejaVu Sans Mono, monospace; font-size: 20px; letter-spacing: 2px; font-weight: bold; }
        dl { margin-top: 20px; display: table; width: 100%; }
        dt, dd { display: table-cell; padding: 4px 0; font-size: 12px; }
        dt { width: 35%; color: #6b7280; }
        dd { font-weight: bold; }
        .verify { margin-top: 18px; font-size: 11px; color: #374151; word-break: break-all; }
    </style>
</head>
<body>
    <div class="card">
        <p class="brand">{{ brand_name() }} Member</p>
        <h1>{{ strtoupper(trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''))) }}</h1>
        <span class="badge">{{ $customer->membershipStatusLabel() }}</span>
        <p class="number">{{ \App\Support\MemberNumberFormatter::display($customer->member_no) }}</p>
        <dl>
            <dt>Issued</dt><dd>{{ optional($customer->membership_issued_at)->format('d M Y') ?? '—' }}</dd>
            <dt>Expires</dt><dd>{{ optional($customer->membership_expires_at)->format('d M Y') ?? '—' }}</dd>
            <dt>Days remaining</dt><dd>{{ max(0, (int) $customer->membershipDaysRemaining()) }}</dd>
        </dl>
        @if (! empty($verifyUrl))
            <p class="verify">Verify online: {{ $verifyUrl }}</p>
        @endif
    </div>
</body>
</html>
