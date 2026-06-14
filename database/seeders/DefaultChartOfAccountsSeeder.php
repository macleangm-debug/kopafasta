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
            ['code' => '1200', 'name' => 'Accrued Interest', 'type' => 'asset'],
            ['code' => '2000', 'name' => 'Capital Partner Funds', 'type' => 'liability'],
            ['code' => '2100', 'name' => 'Customer Deposits', 'type' => 'liability'],
            ['code' => '4000', 'name' => 'Interest Income', 'type' => 'income'],
            ['code' => '4010', 'name' => 'Penalty Income', 'type' => 'income'],
            ['code' => '4020', 'name' => 'Application Fees', 'type' => 'income'],
            ['code' => '4030', 'name' => 'Post Approval Fees', 'type' => 'income'],
            ['code' => '5000', 'name' => 'Commission', 'type' => 'expense'],
            ['code' => '5010', 'name' => 'Collection Costs', 'type' => 'expense'],
            ['code' => '5020', 'name' => 'Legal Costs', 'type' => 'expense'],
            ['code' => '5030', 'name' => 'Bad Debt Expense', 'type' => 'expense'],
        ];

        foreach ($accounts as $row) {
            ChartOfAccount::firstOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name'], 'type' => $row['type'], 'is_active' => true],
            );
        }
    }
}
