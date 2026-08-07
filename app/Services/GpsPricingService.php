<?php

namespace App\Services;

use App\Models\Partner;

class GpsPricingService
{
    /** @return array{device_cost: float, monitoring_total: float, markup: float, total: float, monthly_monitoring: float, months: int} */
    public function estimate(int $loanMonths, ?Partner $partner = null): array
    {
        $defaults = app(PartnerDefaultsService::class);
        $device = $defaults->gpsBaseCost($partner);
        $monthly = $defaults->gpsMonitoringMonthly();
        $markupPct = $defaults->gpsMarkupPercent($partner);
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
