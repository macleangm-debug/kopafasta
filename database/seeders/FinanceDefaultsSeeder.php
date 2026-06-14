<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class FinanceDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        if (Setting::get('finance.write_off_approval_required') === null) {
            Setting::set('finance.write_off_approval_required', true);
        }

        if (Setting::get('finance.capital_partner_interest_share_percent') === null) {
            Setting::set('finance.capital_partner_interest_share_percent', 60);
        }
    }
}
