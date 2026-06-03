<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>SAR Report #{{ $activity->id }}</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
  .header { background: #b91c1c; color: white; padding: 16px 24px; }
  .header h1 { margin: 0; font-size: 18px; }
  .header .sub { font-size: 10px; opacity: 0.9; }
  .body { padding: 24px; }
  h2 { font-size: 12px; margin: 16px 0 6px; color: #b91c1c; text-transform: uppercase; letter-spacing: 1px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  td, th { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; vertical-align: top; }
  th { background: #f9fafb; width: 30%; }
  .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
  .pill-critical { background: #b91c1c; color: white; }
  .pill-high { background: #f97316; color: white; }
  .pill-medium { background: #f59e0b; color: white; }
  .pill-low { background: #6b7280; color: white; }
  .footer { font-size: 9px; color: #6b7280; margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 8px; }
  .sig { margin-top: 36px; }
  .sig .line { border-top: 1px solid #1f2937; width: 280px; padding-top: 4px; }
</style></head>
<body>
  <div class="header">
    <h1>SUSPICIOUS ACTIVITY REPORT</h1>
    <div class="sub">Kopa Fasta Microfinance &middot; SAR-{{ $activity->id }} &middot; Generated {{ $generated->format('d M Y H:i') }}</div>
  </div>

  <div class="body">
    <h2>Activity Summary</h2>
    <table>
      <tr><th>Report ID</th><td>SAR-{{ str_pad((string)$activity->id, 6, '0', STR_PAD_LEFT) }}</td></tr>
      <tr><th>Activity type</th><td>{{ $activity->activity_type }}</td></tr>
      <tr><th>Severity</th><td><span class="pill pill-{{ $activity->severity }}">{{ $activity->severity }}</span></td></tr>
      <tr><th>Status</th><td>{{ ucfirst($activity->status) }}</td></tr>
      <tr><th>Amount involved</th><td>{{ $activity->amount !== null ? 'TZS '.format_number((float)$activity->amount) : '—' }}</td></tr>
      <tr><th>Detected at</th><td>{{ optional($activity->detected_at)->format('d M Y H:i') }}</td></tr>
      <tr><th>Triggered rule</th><td>{{ optional($activity->rule)->name ?? '—' }} ({{ optional($activity->rule)->code }})</td></tr>
    </table>

    <h2>Customer Details</h2>
    <table>
      @if ($activity->customer)
      <tr><th>Customer name</th><td>{{ trim(($activity->customer->first_name ?? '').' '.($activity->customer->last_name ?? '')) }}</td></tr>
      <tr><th>Phone</th><td>{{ $activity->customer->phone ?? '—' }}</td></tr>
      <tr><th>Email</th><td>{{ $activity->customer->email ?? '—' }}</td></tr>
      <tr><th>National ID</th><td>{{ $activity->customer->national_id ?? '—' }}</td></tr>
      <tr><th>Risk band</th><td>{{ ucfirst($activity->customer->risk_band ?? 'unrated') }}</td></tr>
      <tr><th>PEP flag</th><td>{{ $activity->customer->is_pep ? 'YES' : 'No' }}</td></tr>
      @else
      <tr><th>Customer</th><td>(unlinked)</td></tr>
      @endif
      @if ($activity->loan)
      <tr><th>Related loan</th><td>{{ $activity->loan->loan_number }} ({{ $activity->loan->status }})</td></tr>
      @endif
    </table>

    <h2>Description</h2>
    <p>{{ $activity->description }}</p>

    <h2>Investigator Notes</h2>
    <p>{!! nl2br(e($activity->investigator_notes ?: '(none recorded)')) !!}</p>

    <div class="sig">
      <div class="line">
        Reporting Officer: {{ $generator?->name ?? '—' }}<br>
        Date: {{ $generated->format('d M Y') }}
      </div>
    </div>

    <div class="footer">
      This report is prepared in compliance with Bank of Tanzania AML/CFT regulations and must be submitted to the
      Financial Intelligence Unit (FIU) within 24 hours of confirmation. Contents are CONFIDENTIAL.
    </div>
  </div>
</body></html>
