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

        if (Setting::get('loan.collateral_requirement_mode') === null) {
            Setting::set('loan.collateral_requirement_mode', 'above_amount');
        }

        if (Setting::get('loan.collateral_required_above') === null) {
            Setting::set('loan.collateral_required_above', 200_000);
        }
    }
}
