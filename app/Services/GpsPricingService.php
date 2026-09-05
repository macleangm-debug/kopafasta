<?php

namespace App\Services;

use App\Models\Partner;

class GpsPricingService
{
    /**
     * Single GPS calculator: base install + markup, base monitoring + markup.
     * Do not price from standalone ChargesFee GPS_FEE.amount at runtime.
     *
     * @return array{
     *   device_cost: float,
     *   device_markup: float,
     *   device_borrower: float,
     *   monitoring_total: float,
     *   monitoring_markup: float,
     *   monitoring_borrower: float,
     *   monthly_monitoring: float,
     *   monthly_monitoring_borrower: float,
     *   markup_percent: float,
     *   markup: float,
     *   total: float,
     *   months: int
     * }
     */
    public function estimate(int $loanMonths, ?Partner $partner = null): array
    {
        $defaults = app(PartnerDefaultsService::class);
        $device = $defaults->gpsBaseCost($partner);
        $monthly = $defaults->gpsMonitoringMonthly();
        $markupPct = $defaults->gpsMarkupPercent($partner);
        $months = max(1, $loanMonths);

        $deviceMarkup = round($device * ($markupPct / 100), 2);
        $deviceBorrower = round($device + $deviceMarkup, 2);

        $monitoringBaseTotal = round($monthly * $months, 2);
        $monitoringMarkup = round($monitoringBaseTotal * ($markupPct / 100), 2);
        $monitoringBorrower = round($monitoringBaseTotal + $monitoringMarkup, 2);
        $monthlyBorrower = round($monthly * (1 + ($markupPct / 100)), 2);

        $markup = round($deviceMarkup + $monitoringMarkup, 2);

        return [
            'device_cost'                   => $device,
            'device_markup'                 => $deviceMarkup,
            'device_borrower'               => $deviceBorrower,
            'monitoring_total'              => $monitoringBaseTotal,
            'monitoring_markup'             => $monitoringMarkup,
            'monitoring_borrower'           => $monitoringBorrower,
            'monthly_monitoring'            => $monthly,
            'monthly_monitoring_borrower'   => $monthlyBorrower,
            'months'                        => $months,
            'markup_percent'                => $markupPct,
            'markup'                        => $markup,
            'total'                         => round($deviceBorrower + $monitoringBorrower, 2),
        ];
    }
}
