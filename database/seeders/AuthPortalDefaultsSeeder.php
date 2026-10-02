<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class AuthPortalDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set('auth_portal.require_2fa_admin', true);
        Setting::set('auth_portal.require_2fa_staff', true);
        Setting::set('auth_portal.staff_allow_authenticator', true);
        Setting::set('auth_portal.staff_allow_security_questions', true);
        Setting::set('auth_portal.privileged_require_authenticator', true);
    }
}
