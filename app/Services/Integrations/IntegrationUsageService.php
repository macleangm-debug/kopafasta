<?php

namespace App\Services\Integrations;

use App\Models\CustomerPayment;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Services\CrbBillingService;
use Carbon\CarbonImmutable;

class IntegrationUsageService
{
    public function billing(string $partnerKey): array
    {
        $stored = Setting::get("integrations.billing.{$partnerKey}");

        return is_array($stored) ? $stored : [];
    }

    public function saveBilling(string $partnerKey, array $data): void
    {
        Setting::set("integrations.billing.{$partnerKey}", $data);
    }

    /**
     * @return array{
     *   month: string,
     *   cards: list<array{label: string, value: string, hint?: string}>,
     *   history: list<array{month: string, metrics: array<string, string|int|float>}>
     * }
     */
    public function usage(string $partnerKey, ?string $category = null): array
    {
        $partner = app(IntegrationCatalog::class)->partner($partnerKey);
        $category ??= $partner['category'] ?? 'payment';

        return match ($category) {
            'payment' => $this->paymentUsage($partnerKey),
            'messaging' => $this->messagingUsage($partnerKey),
            'compliance' => $this->complianceUsage(),
            default => [
                'month' => now()->format('F Y'),
                'cards' => [
                    ['label' => 'Usage', 'value' => '—', 'hint' => 'No usage probe for this partner yet.'],
                ],
                'history' => [],
            ],
        };
    }

    protected function paymentUsage(string $partnerKey): array
    {
        $billing = $this->billing($partnerKey);
        $month = CarbonImmutable::now()->startOfMonth();
        $summary = $this->paymentMonthSummary($partnerKey, $month, $billing);

        $history = collect(range(0, 5))->map(function (int $offset) use ($partnerKey, $billing) {
            $m = CarbonImmutable::now()->subMonths($offset)->startOfMonth();
            $row = $this->paymentMonthSummary($partnerKey, $m, $billing);

            return [
                'month' => $row['month'],
                'metrics' => [
                    'Mobile' => $row['mobile_count'],
                    'Bank' => $row['bank_count'],
                    'Collections' => format_money($row['volume']),
                    'Disbursements' => format_money($row['disbursement_volume']),
                    'Est. charges' => format_money($row['estimated_charges']),
                ],
            ];
        })->all();

        return [
            'month' => $summary['month'],
            'cards' => [
                ['label' => 'Mobile transactions', 'value' => number_format($summary['mobile_count']), 'hint' => $summary['month']],
                ['label' => 'Bank transactions', 'value' => number_format($summary['bank_count']), 'hint' => $summary['month']],
                ['label' => 'Collection volume', 'value' => format_money($summary['volume']), 'hint' => 'Pay-ins this month'],
                ['label' => 'Est. partner charges', 'value' => format_money($summary['estimated_charges']), 'hint' => 'Collections + disbursements'],
            ],
            'history' => $history,
        ];
    }

    /** @return array{month: string, mobile_count: int, bank_count: int, volume: float, estimated_charges: float} */
    protected function paymentMonthSummary(string $partnerKey, CarbonImmutable $month, array $billing): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $query = CustomerPayment::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['paid', 'verified', 'processing']);

        if ($partnerKey === 'payin') {
            $query->where(function ($q) {
                $q->where('provider', 'payin')
                    ->orWhere(function ($q2) {
                        $q2->whereNull('provider')->where('payment_method', 'mobile_money');
                    });
            });
        } else {
            $query->where('provider', $partnerKey);
        }

        $rows = $query->get(['payment_method', 'amount']);
        $mobile = $rows->where('payment_method', 'mobile_money');
        $bank = $rows->where('payment_method', 'bank_transfer');
        $volume = (float) $rows->sum('amount');
        $txnCount = $rows->count();

        $disbursed = \App\Models\Disbursement::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('released_at')
            ->get(['amount']);
        $disbursementVolume = (float) $disbursed->sum('amount');
        $disbursementCount = $disbursed->count();

        return [
            'month' => $start->format('F Y'),
            'mobile_count' => $mobile->count(),
            'bank_count' => $bank->count(),
            'volume' => $volume,
            'disbursement_count' => $disbursementCount,
            'disbursement_volume' => $disbursementVolume,
            'estimated_charges' => $this->estimatePaymentCharges(
                (float) $rows->sum('amount'),
                $txnCount,
                $disbursementVolume,
                $disbursementCount,
                $billing,
            ),
        ];
    }

    protected function estimatePaymentCharges(
        float $collectionVolume,
        int $collectionCount,
        float $disbursementVolume,
        int $disbursementCount,
        array $billing,
    ): float {
        $collection = $this->applyFee(
            $billing['collection_fee_type'] ?? 'percent',
            (float) ($billing['collection_fee_value'] ?? 0),
            $collectionVolume,
            $collectionCount,
        );
        $disbursement = $this->applyFee(
            $billing['disbursement_fee_type'] ?? 'percent',
            (float) ($billing['disbursement_fee_value'] ?? 0),
            $disbursementVolume,
            $disbursementCount,
        );

        return round($collection + $disbursement, 2);
    }

    protected function applyFee(string $type, float $value, float $volume, int $count): float
    {
        if ($value <= 0) {
            return 0.0;
        }

        return $type === 'fixed'
            ? round($value * $count, 2)
            : round($volume * ($value / 100), 2);
    }

    protected function messagingUsage(string $partnerKey): array
    {
        $billing = $this->billing($partnerKey);
        $smsFee = (float) ($billing['sms_fee'] ?? 0);
        $emailFee = (float) ($billing['email_fee'] ?? 0);
        $channel = $partnerKey === 'email_smtp' ? 'email' : 'sms';

        $history = collect(range(0, 5))->map(function (int $offset) use ($channel, $smsFee, $emailFee) {
            $m = CarbonImmutable::now()->subMonths($offset)->startOfMonth();
            $count = NotificationLog::query()
                ->where('channel', $channel)
                ->whereBetween('created_at', [$m->startOfMonth(), $m->endOfMonth()])
                ->count();
            $fee = $channel === 'email' ? $emailFee : $smsFee;

            return [
                'month' => $m->format('F Y'),
                'metrics' => [
                    'Messages' => $count,
                    'Est. charges' => format_money($count * $fee),
                ],
            ];
        })->all();

        $thisMonth = $history[0]['metrics']['Messages'] ?? 0;
        $fee = $channel === 'email' ? $emailFee : $smsFee;

        return [
            'month' => now()->format('F Y'),
            'cards' => [
                ['label' => strtoupper($channel).' sent', 'value' => number_format((int) $thisMonth), 'hint' => now()->format('F Y')],
                ['label' => 'Rate', 'value' => format_money($fee), 'hint' => 'Per message'],
                ['label' => 'Est. charges', 'value' => format_money(((int) $thisMonth) * $fee), 'hint' => 'This month'],
            ],
            'history' => $history,
        ];
    }

    protected function complianceUsage(): array
    {
        $billing = app(CrbBillingService::class);
        $summary = $billing->monthlySummary();
        $history = $billing->recentMonths(6)->map(fn (array $row) => [
            'month' => $row['month'],
            'metrics' => [
                'Requests' => $row['requests'],
                'Est. cost' => format_money($row['estimated_cost']),
            ],
        ])->all();

        $package = $this->billing('crb');
        $included = (int) ($package['included_units'] ?? 0);
        $overage = (float) ($package['overage_fee'] ?? $billing->costPerRequest());

        return [
            'month' => $summary['month'],
            'cards' => [
                ['label' => 'Requests this month', 'value' => number_format($summary['requests']), 'hint' => $summary['month']],
                ['label' => 'Included package', 'value' => $included > 0 ? number_format($included) : '—', 'hint' => 'Calls in package'],
                ['label' => 'Overage rate', 'value' => format_money($overage), 'hint' => 'Per call after package'],
                ['label' => 'Est. spend', 'value' => format_money($summary['estimated_cost']), 'hint' => 'From CRB cost settings'],
            ],
            'history' => $history,
        ];
    }
}
