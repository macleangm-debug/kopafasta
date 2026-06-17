<?php

return [
    'max_mismatch_attempts' => (int) env('NIDA_MAX_MISMATCH_ATTEMPTS', 3),
    'lock_hours'            => env('NIDA_LOCK_HOURS') !== null ? (int) env('NIDA_LOCK_HOURS') : 24,
    'lock_days'             => (int) env('NIDA_LOCK_DAYS', 1),
    'require_dob'           => (bool) env('NIDA_REQUIRE_DOB', true),
];
