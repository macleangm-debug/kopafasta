<?php

return [
    'max_mismatch_attempts' => (int) env('NIDA_MAX_MISMATCH_ATTEMPTS', 3),
    'lock_hours' => (int) env('NIDA_LOCK_HOURS', 24),
];
