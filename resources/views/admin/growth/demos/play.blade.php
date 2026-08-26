<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $presentation ? '' : 'Demo · ' }}{{ $demo->display_name }}</title>
    @vite(['resources/css/app.css'])
</head>
@php
    $p = $demo->payload ?? [];
    $scenario = (string) ($p['scenario'] ?? $demo->scenario_key);
    $name = $p['name'] ?? $demo->display_name;
    $grade = $p['grade'] ?? 'gold';
    $plus = ! empty($p['plus']);
    $amount = (float) ($p['amount'] ?? 0);
    $money = fn ($value) => 'TZS '.number_format((float) $value);
@endphp
<body class="min-h-screen {{ $presentation ? 'bg-slate-950' : 'bg-[#f4f7f5]' }} text-gray-900 antialiased">
<div class="{{ $presentation ? 'min-h-screen grid place-items-center py-8 px-4' : 'mx-auto max-w-md py-6 px-4' }}">
    @unless ($presentation)
        <p class="text-[10px] font-bold uppercase tracking-widest text-amber-800 mb-3">Marketing demo · not a real customer · money movement blocked</p>
    @endunless

    <div class="{{ $presentation ? 'w-[390px] max-w-full' : '' }}">
        <div class="rounded-[2.4rem] bg-black p-3 shadow-2xl {{ $presentation ? 'ring-8 ring-black/40' : '' }}">
            <div class="rounded-[2rem] overflow-hidden bg-[#f4f7f5] min-h-[680px] flex flex-col">
                <div class="bg-brand text-white px-5 pt-8 pb-5 relative overflow-hidden">
                    <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-brand-gold/20 pointer-events-none"></div>
                    <div class="flex items-start justify-between gap-3 relative">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">Kopafasta</p>
                            <p class="text-lg font-bold mt-1 leading-tight">{{ $name }}</p>
                            <p class="text-xs text-white/75 mt-0.5">{{ $p['membership_no'] ?? 'KF-DEMO' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-brand-gold text-brand text-[10px] font-bold uppercase tracking-wide px-2.5 py-1">{{ strtoupper((string) $grade) }}{{ $plus ? ' · Plus' : '' }}</span>
                    </div>
                </div>

                <div class="flex-1 p-4 space-y-3">
                    @if (in_array($scenario, ['loan_received', 'making_repayment', 'loan_completed'], true))
                        <div class="rounded-2xl {{ $scenario === 'loan_completed' ? 'bg-emerald-600' : 'bg-gray-900' }} text-white p-4">
                            <p class="text-[10px] uppercase tracking-widest text-white/70">
                                {{ $scenario === 'loan_received' ? 'Loan received' : ($scenario === 'making_repayment' ? 'Active loan' : 'Loan completed') }}
                            </p>
                            <p class="mt-2 text-3xl font-bold tabular-nums">{{ $money($scenario === 'loan_completed' ? 0 : ($p['loan_balance'] ?? $amount)) }}</p>
                            <p class="text-xs text-white/70 mt-1">
                                @if ($scenario === 'loan_received')
                                    Disbursed to your account. This is a demonstration only.
                                @elseif ($scenario === 'making_repayment')
                                    Next {{ $money($p['next_payment'] ?? 0) }} due {{ $p['next_due'] ?? 'soon' }}
                                @else
                                    Paid in full. Grade and Trust stay with you.
                                @endif
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-2xl bg-white ring-1 ring-gray-100 p-3">
                                <p class="text-[10px] uppercase tracking-widest text-gray-500">Paid</p>
                                <p class="font-bold tabular-nums">{{ $money($p['amount_paid'] ?? 0) }}</p>
                            </div>
                            <div class="rounded-2xl bg-white ring-1 ring-gray-100 p-3">
                                <p class="text-[10px] uppercase tracking-widest text-gray-500">Facility</p>
                                <p class="font-bold tabular-nums">{{ $money($amount) }}</p>
                            </div>
                        </div>
                    @endif

                    @if ($scenario === 'grade_state' || $scenario === 'grade_upgraded')
                        <div class="rounded-2xl kf-premium-panel p-4 bg-brand text-white">
                            <p class="text-[10px] uppercase tracking-widest text-brand-gold">Your grade</p>
                            <p class="text-3xl font-bold mt-1">{{ ucfirst((string) $grade) }}</p>
                            <p class="text-sm text-white/80 mt-2">Grade is earned from repayment behaviour. It is never bought.</p>
                        </div>
                    @endif

                    @if ($scenario === 'trust_state')
                        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
                            <p class="text-[10px] uppercase tracking-widest text-gray-500">Trust</p>
                            <p class="text-4xl font-bold tabular-nums text-brand mt-1">{{ $p['trust'] ?? 70 }}</p>
                            <div class="mt-3 h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-brand-gold" style="width: {{ min(100, (int) ($p['trust'] ?? 70)) }}%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Trust grows when you repay on time. This demo cannot change a real score.</p>
                        </div>
                    @endif

                    @if (in_array($scenario, ['plus_active', 'monthly_report_ready'], true))
                        <div class="rounded-2xl bg-brand text-white p-4">
                            <p class="text-[10px] uppercase tracking-widest text-brand-gold">Kopafasta Plus</p>
                            <p class="text-xl font-bold mt-1">{{ $scenario === 'monthly_report_ready' ? 'Monthly report ready' : 'Plus member' }}</p>
                            <p class="text-sm text-white/80 mt-1">{{ $p['report_month'] ?? now()->format('F Y') }} · independent of Grade.</p>
                        </div>
                    @endif

                    @if ($scenario === 'goal_progress')
                        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
                            <p class="text-[10px] uppercase tracking-widest text-gray-500">Goal</p>
                            <p class="font-bold mt-1">{{ $p['goal_name'] ?? 'Shop stock' }}</p>
                            <p class="text-3xl font-bold tabular-nums text-brand mt-2">{{ (int) ($p['goal_percent'] ?? 40) }}%</p>
                            <div class="mt-2 h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-brand" style="width: {{ min(100, (int) ($p['goal_percent'] ?? 40)) }}%"></div>
                            </div>
                            <p class="text-sm text-gray-600 mt-2">Saved {{ $money($p['goal_saved'] ?? 0) }}</p>
                        </div>
                    @endif

                    @if (in_array($scenario, ['affiliate_commission_earned', 'affiliate_withdrawal'], true))
                        <div class="rounded-2xl bg-gray-900 text-white p-4">
                            <p class="text-[10px] uppercase tracking-widest text-brand-gold">Affiliate</p>
                            <p class="text-sm mt-1">{{ $scenario === 'affiliate_withdrawal' ? 'Available to withdraw' : 'Commission earned' }}</p>
                            <p class="text-3xl font-bold tabular-nums mt-2">{{ $money($scenario === 'affiliate_withdrawal' ? ($p['affiliate_available'] ?? 0) : ($p['affiliate_earnings'] ?? 0)) }}</p>
                            <p class="text-xs text-white/60 mt-2">Presentation only. Withdrawal and PayIn are blocked by DemoGuard.</p>
                        </div>
                    @endif

                    <p class="text-[11px] text-gray-400 text-center pt-2">Expires {{ optional($demo->expires_at)->format('d M H:i') }} · Isolated identity</p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
