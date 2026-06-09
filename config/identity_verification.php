<?php

return [
    'max_mismatch_attempts' => (int) env('NIDA_MAX_MISMATCH_ATTEMPTS', 3),
    'lock_days'             => (int) env('NIDA_LOCK_DAYS', 30),
    'require_dob'           => (bool) env('NIDA_REQUIRE_DOB', true),
];
