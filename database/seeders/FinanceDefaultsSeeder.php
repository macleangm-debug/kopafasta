<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
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

        $map = [
            'finance.cash_gl_account_id'               => '1010',
            'finance.loan_receivable_gl_account_id'    => '1100',
            'finance.capital_partner_pool_gl_account_id' => '2000',
            'finance.fee_income_gl_account_id'         => '4020',
        ];

        foreach ($map as $key => $code) {
            if (Setting::get($key) !== null) {
                continue;
            }

            $account = ChartOfAccount::query()->where('code', $code)->value('id');
            if ($account) {
                Setting::set($key, (int) $account);
            }
        }
    }
}
