<?php

return [
    'pin_length'            => 4,
    'max_login_attempts'    => 5,
    'lockout_minutes'       => 15,
    'trusted_device_days'   => 30,
    'pin_reset_otp_minutes' => 10,
    'biometric_enabled'     => false,
    'trusted_device_cookie' => 'kopafasta_trusted_device',

    /** Require TOTP for web login (enrollment prompted when missing). */
    'require_2fa_admin'   => (bool) env('REQUIRE_2FA_ADMIN', false),
    'require_2fa_staff'   => (bool) env('REQUIRE_2FA_STAFF', false),
    'require_2fa_partner' => (bool) env('REQUIRE_2FA_PARTNER', false),
    'two_factor_session_hours' => (int) env('TWO_FACTOR_SESSION_HOURS', 12),
];
