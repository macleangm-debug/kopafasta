@php
    $money = $report['money'] ?? [];
    $biz = $report['business'] ?? [];
    $left = (float) ($money['left'] ?? ((float) ($money['in'] ?? 0) - (float) ($money['out'] ?? 0)));
    $review = $report['observations'] ?? [];
    if ($review === []) {
        $review = $report['noticed'] ?? [];
    }
    $businessContext = $report['business_context'] ?? __('plus.business.all_businesses');
    $mark = pdf_brand_mark_path();
    $brand = brand_name();
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title></title>
    <style>
        @page { margin: 14mm 12mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; margin: 0; }
        .header { background: #0f3d2e; color: #fff; padding: 16px 18px; border-radius: 12px; }
        .header .kicker { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #f5c842; font-weight: bold; }
        .header h1 { margin: 8px 0 4px; font-size: 18px; text-align: center; }
        .header .meta { text-align: center; color: rgba(255,255,255,0.85); font-size: 10px; margin: 2px 0; }
        .brand-row { width: 100%; }
        .brand-row td { vertical-align: middle; }
        .brand-row img { height: 28px; }
        .wordmark { font-size: 14px; font-weight: bold; }
        .card { margin-top: 12px; padding: 12px 14px; border: 1px solid #d1fae5; border-radius: 10px; background: #f0fdf4; }
        .card h2 { margin: 0 0 8px; font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: #0f3d2e; }
        .kpi td { width: 25%; padding: 6px 4px; }
        .kpi .label { font-size: 8px; text-transform: uppercase; color: #6b7280; letter-spacing: 1px; }
        .kpi .value { font-size: 13px; font-weight: bold; margin-top: 2px; }
        .obs { margin-bottom: 8px; }
        .obs .title { font-weight: bold; }
        .obs .body { color: #4b5563; margin-top: 2px; }
        .footer { position: fixed; left: 0; right: 0; bottom: -8mm; font-size: 9px; color: #4b5563; }
        .footer table { width: 100%; }
        .footer .lockup img { height: 12px; vertical-align: middle; }
        .footer .lockup span { font-weight: bold; font-size: 10px; color: #111827; padding-left: 6px; }
        .footer .conf { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <div class="header">
        <table class="brand-row" cellspacing="0" cellpadding="0">
            <tr>
                <td>
                    @if ($mark)
                        <img src="{{ $mark }}" alt="">
                    @endif
                    <span class="wordmark">{{ pdf_text($brand) }}</span>
                </td>
                <td style="text-align:right"><span class="kicker">Kopafasta Plus</span></td>
            </tr>
        </table>
        <div class="meta">{{ pdf_text(__('plus.reports.a4_kicker')) }}</div>
        <div class="meta">
            {{ pdf_text($report['member_name'] ?? '') }}
            @if (! empty($report['membership_number']))
                · {{ pdf_text($report['membership_number']) }}
            @endif
            · {{ pdf_text($report['grade'] ?? '') }}
        </div>
        <h1>{{ pdf_text($report['label'] ?? '') }}</h1>
        <div class="meta">{{ pdf_text(__('plus.reports.trust_line', ['percent' => $report['trust_percent'] ?? 0, 'label' => $report['trust']['label'] ?? ''])) }}</div>
        <div class="meta">{{ pdf_text(__('plus.reports.business_context', ['name' => $businessContext])) }}</div>
    </div>

    <table class="kpi" width="100%" cellspacing="0" cellpadding="0" style="margin-top:12px">
        <tr>
            <td>
                <div class="label">{{ pdf_text(__('plus.money.in')) }}</div>
                <div class="value">{{ format_money_compact($money['in'] ?? 0) }}</div>
            </td>
            <td>
                <div class="label">{{ pdf_text(__('plus.money.out')) }}</div>
                <div class="value">{{ format_money_compact($money['out'] ?? 0) }}</div>
            </td>
            <td>
                <div class="label">{{ pdf_text(__('plus.reports.kpi_left')) }}</div>
                <div class="value">{{ format_money_compact($left) }}</div>
            </td>
            <td>
                <div class="label">{{ pdf_text(__('plus.reports.kpi_goals')) }}</div>
                <div class="value">+{{ format_money_compact($report['goals_added'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    @if ($review !== [])
        <div class="card">
            <h2>{{ pdf_text(__('plus.reports.your_review')) }}</h2>
            @foreach ($review as $obs)
                <div class="obs">
                    <div class="title">{{ pdf_text($obs['title'] ?? '') }}</div>
                    <div class="body">{{ pdf_text($obs['body'] ?? '') }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card">
        <h2>{{ pdf_text(__('plus.reports.money')) }}</h2>
        @if (! empty($report['has_money']))
            <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td>{{ pdf_text(__('plus.money.in')) }}<br><strong>{{ format_money($money['in'] ?? 0) }}</strong></td>
                    <td>{{ pdf_text(__('plus.money.out')) }}<br><strong>{{ format_money($money['out'] ?? 0) }}</strong></td>
                    <td>{{ pdf_text(__('plus.money.left_label')) }}<br><strong>{{ format_money($left) }}</strong></td>
                </tr>
            </table>
        @else
            <p>{{ pdf_text(__('plus.reports.empty_money')) }}</p>
        @endif
    </div>

    <div class="card" style="background:#fff">
        <h2>{{ pdf_text(__('plus.reports.business')) }}</h2>
        <p>{{ pdf_text($businessContext) }}</p>
        @if (! empty($report['has_business']))
            <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td>{{ pdf_text(__('plus.business.sold')) }}<br><strong>{{ format_money($biz['sold'] ?? 0) }}</strong></td>
                    <td>{{ pdf_text(__('plus.business.spent')) }}<br><strong>{{ format_money($biz['spent'] ?? 0) }}</strong></td>
                    <td>{{ pdf_text(__('plus.business.diff')) }}<br><strong>{{ format_money($biz['difference'] ?? 0) }}</strong></td>
                </tr>
            </table>
        @else
            <p>{{ pdf_text(__('plus.reports.empty_business')) }}</p>
        @endif
    </div>

    <div class="card" style="background:#fffbeb;border-color:#fde68a">
        <h2>{{ pdf_text(__('plus.reports.goals')) }}</h2>
        @forelse ($report['goal_cards'] ?? [] as $card)
            <p style="margin:0 0 6px">
                <strong>{{ pdf_text(($card['icon'] ?? '').' '.($card['title'] ?? '')) }} · {{ (int) ($card['percent'] ?? 0) }}%</strong><br>
                {{ format_money($card['saved'] ?? 0) }} / {{ format_money($card['target'] ?? 0) }}
            </p>
        @empty
            <p>{{ pdf_text(__('plus.reports.empty_goals')) }}</p>
        @endforelse
    </div>

    <div class="footer">
        <table cellspacing="0" cellpadding="0">
            <tr>
                <td class="lockup">
                    @if ($mark)
                        <img src="{{ $mark }}" alt="">
                    @endif
                    <span>{{ pdf_text($brand) }}</span>
                </td>
                <td class="conf">{{ pdf_text(__('plus.reports.footer_confidential')) }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
