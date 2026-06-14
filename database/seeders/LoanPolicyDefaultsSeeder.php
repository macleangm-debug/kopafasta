<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class LoanPolicyDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        if (Setting::get('loan.max_active_loans') === null) {
            Setting::set('loan.max_active_loans', 1);
        }

        if (Setting::get('loan.allow_restructure') === null) {
            Setting::set('loan.allow_restructure', false);
        }
    }
}
