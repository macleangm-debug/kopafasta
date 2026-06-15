<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class DefaultChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Cash', 'type' => 'asset'],
            ['code' => '1010', 'name' => 'Bank', 'type' => 'asset'],
            ['code' => '1020', 'name' => 'Mobile Money Float', 'type' => 'asset'],
            ['code' => '1100', 'name' => 'Loans Receivable', 'type' => 'asset'],
            ['code' => '1200', 'name' => 'Accrued Interest Receivable', 'type' => 'asset'],
            ['code' => '2000', 'name' => 'Capital Partner Funds', 'type' => 'liability'],
            ['code' => '2100', 'name' => 'Borrower Refunds Payable', 'type' => 'liability'],
            ['code' => '2110', 'name' => 'Deferred Fee Liability', 'type' => 'liability'],
            ['code' => '2120', 'name' => 'Recovery Partner Payable', 'type' => 'liability'],
            ['code' => '4000', 'name' => 'Interest Income', 'type' => 'income'],
            ['code' => '4010', 'name' => 'Penalty Income', 'type' => 'income'],
            ['code' => '4020', 'name' => 'Application Fee Income', 'type' => 'income'],
            ['code' => '4030', 'name' => 'Registration Fee Income', 'type' => 'income'],
            ['code' => '4040', 'name' => 'Recovery Revenue', 'type' => 'income'],
            ['code' => '4050', 'name' => 'Valuation Revenue', 'type' => 'income'],
            ['code' => '4060', 'name' => 'GPS Revenue', 'type' => 'income'],
            ['code' => '5000', 'name' => 'Bad Debt Expense', 'type' => 'expense'],
            ['code' => '5010', 'name' => 'Recovery Partner Expense', 'type' => 'expense'],
            ['code' => '5020', 'name' => 'Valuation Expense', 'type' => 'expense'],
            ['code' => '5030', 'name' => 'GPS Expense', 'type' => 'expense'],
            ['code' => '5040', 'name' => 'Legal Expense', 'type' => 'expense'],
            ['code' => '5050', 'name' => 'Default Expense', 'type' => 'expense'],
        ];

        foreach ($accounts as $row) {
            ChartOfAccount::firstOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name'], 'type' => $row['type'], 'is_active' => true],
            );
        }
    }
}
