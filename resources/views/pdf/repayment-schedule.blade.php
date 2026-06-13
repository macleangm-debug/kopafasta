<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Repayment Schedule — {{ $agreement->reference }}</title>
<style>
    @page { margin: 24mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.55; }
    h1 { font-size: 18px; margin: 0 0 4px; color: #b45309; }
    h2 { font-size: 13px; margin: 18px 0 6px; color: #374151; text-transform: uppercase; letter-spacing: 1px; }
    .muted { color: #6b7280; font-size: 10px; }
    .header { border-bottom: 2px solid #b45309; padding-bottom: 10px; margin-bottom: 14px; }
    table.kv { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 4px 6px; vertical-align: top; }
    table.kv td.label { color: #6b7280; width: 38%; font-size: 10px; text-transform: uppercase; }
    table.kv td.value { color: #111827; font-weight: 600; }
    table.schedule { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 10px; }
    table.schedule th, table.schedule td { border: 1px solid #e5e7eb; padding: 4px 6px; text-align: left; }
    table.schedule th { background: #f9fafb; text-transform: uppercase; font-size: 9px; }
    .footer { margin-top: 30px; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ brand('legal_name') }}</h1>
    <div class="muted">Repayment Schedule Annex · Reference: {{ $agreement->reference }}</div>
</div>

<p>
    This schedule annex forms part of the loan agreement for application
    <strong>{{ $snapshot['application_number'] }}</strong> and borrower
    <strong>{{ $snapshot['customer_name'] }}</strong>.
</p>

<h2>Loan summary</h2>
<table class="kv">
    <tr><td class="label">Disbursement date</td><td class="value">{{ $snapshot['disbursement_date'] ? \Illuminate\Support\Carbon::parse($snapshot['disbursement_date'])->format('d M Y') : '—' }}</td></tr>
    <tr><td class="label">Principal</td><td class="value">{{ format_money($snapshot['principal']) }}</td></tr>
    <tr><td class="label">First payment</td><td class="value">{{ $snapshot['first_due_date'] ? \Illuminate\Support\Carbon::parse($snapshot['first_due_date'])->format('d M Y') : '—' }}</td></tr>
    <tr><td class="label">Final payment</td><td class="value">{{ $snapshot['last_due_date'] ? \Illuminate\Support\Carbon::parse($snapshot['last_due_date'])->format('d M Y') : '—' }}</td></tr>
    <tr><td class="label">Instalment</td><td class="value">{{ format_money($snapshot['estimated_emi'] ?? 0) }}</td></tr>
    <tr><td class="label">Frequency</td><td class="value">{{ ucfirst($snapshot['repayment_cadence'] ?? 'weekly') }}</td></tr>
</table>

<h2>Instalment schedule</h2>
<table class="schedule">
    <thead>
        <tr>
            <th>#</th>
            <th>Due date</th>
            <th>Principal</th>
            <th>Interest</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach (($snapshot['repayment_schedule'] ?? []) as $row)
            <tr>
                <td>{{ $row['installment_no'] }}</td>
                <td>{{ \Illuminate\Support\Carbon::parse($row['due_date'])->format('d M Y') }}</td>
                <td>{{ format_money($row['principal_due']) }}</td>
                <td>{{ format_money($row['interest_due']) }}</td>
                <td>{{ format_money($row['total_due']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    Generated {{ now()->format('d M Y') }} · {{ brand('legal_name') }} · {{ brand('support_email') }}
</div>

</body>
</html>
