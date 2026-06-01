<?php

namespace App\Services;

class GpsPricingService
{
    /** @return array{device_cost: float, monitoring_total: float, markup: float, total: float, monthly_monitoring: float, months: int} */
    public function estimate(int $loanMonths): array
    {
        $device = (float) config('gps_pricing.device_cost', 100_000);
        $monthly = (float) config('gps_pricing.monitoring_monthly', 10_000);
        $markupPct = (float) config('gps_pricing.markup_percent', 10);
        $months = max(1, $loanMonths);

        $monitoringTotal = round($monthly * $months, 2);
        $subtotal = $device + $monitoringTotal;
        $markup = round($subtotal * ($markupPct / 100), 2);

        return [
            'device_cost'         => $device,
            'monitoring_total'    => $monitoringTotal,
            'monthly_monitoring'  => $monthly,
            'months'              => $months,
            'markup'              => $markup,
            'total'               => round($subtotal + $markup, 2),
        ];
    }
}
