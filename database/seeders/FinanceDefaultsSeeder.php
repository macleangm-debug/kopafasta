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
            'finance.cash_gl_account_id'                    => '1010',
            'finance.loan_receivable_gl_account_id'       => '1100',
            'finance.capital_partner_pool_gl_account_id'    => '2000',
            'finance.borrower_refunds_payable_gl_account_id' => '2100',
            'finance.deferred_fee_liability_gl_account_id'  => '2110',
            'finance.recovery_partner_payable_gl_account_id'=> '2120',
            'finance.supplier_payable_gl_account_id'        => '2130',
            'finance.asset_lending_principal_clearing_gl_account_id' => '1100',
            'finance.fee_income_gl_account_id'            => '4020',
            'finance.application_fee_income_gl_account_id'  => '4020',
            'finance.registration_fee_income_gl_account_id' => '4030',
            'finance.interest_income_gl_account_id'         => '4000',
            'finance.penalty_income_gl_account_id'          => '4010',
            'finance.recovery_revenue_gl_account_id'        => '4040',
            'finance.valuation_revenue_gl_account_id'       => '4050',
            'finance.gps_revenue_gl_account_id'             => '4060',
            'finance.asset_lending_revenue_gl_account_id'   => '4070',
            'finance.bad_debt_expense_gl_account_id'        => '5000',
            'finance.default_expense_gl_account_id'         => '5050',
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
