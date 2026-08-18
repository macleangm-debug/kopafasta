<?php

return [
    'device_cost' => (float) env('GPS_DEVICE_COST', 50_000),
    'monitoring_monthly' => (float) env('GPS_MONITORING_MONTHLY', 20_000),
    'markup_percent' => (float) env('GPS_MARKUP_PERCENT', 0),
    'fee_codes' => ['GPS', 'GPS_BUNDLE', 'GPS_DEVICE', 'GPS_FEE'],
];
