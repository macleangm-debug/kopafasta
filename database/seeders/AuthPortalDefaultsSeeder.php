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
    }
}
