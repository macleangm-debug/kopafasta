<?php

return [
    'code_prefix' => env('AFFILIATE_CODE_PREFIX', 'KPA'),

    'default_registration_discount_percent' => (float) env('AFFILIATE_REGISTRATION_DISCOUNT', 10),
    'default_application_discount_percent' => (float) env('AFFILIATE_APPLICATION_DISCOUNT', 10),
    'default_commission_percent' => (float) env('AFFILIATE_COMMISSION_PERCENT', 10),
];
