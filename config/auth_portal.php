<?php

return [
    'pin_length'            => 4,
    'max_login_attempts'    => 5,
    'lockout_minutes'       => 15,
    'trusted_device_days'   => 30,
    'pin_reset_otp_minutes' => 10,
    'biometric_enabled'     => false,
    'trusted_device_cookie' => 'kopafasta_trusted_device',

    /** Require TOTP for web login (enrollment prompted when missing). Every new login must pass a code. */
    'require_2fa_admin'   => (bool) env('REQUIRE_2FA_ADMIN', true),
    'require_2fa_staff'   => (bool) env('REQUIRE_2FA_STAFF', true),
    'require_2fa_partner' => (bool) env('REQUIRE_2FA_PARTNER', false),
    'two_factor_session_hours' => (int) env('TWO_FACTOR_SESSION_HOURS', 12),
];
