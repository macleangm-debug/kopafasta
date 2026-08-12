<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

class LoanProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'code' => 'BIZ-30',
                'name' => 'Business Booster 30',
                'description' => 'Short-term working capital loan for small businesses, repaid over 1–3 months.',
                'category' => 'business_loan',
                'interest_rate' => 0.0500,
                'tenure_min_months' => 1,
                'tenure_max_months' => 3,
                'min_amount' => 100000,
                'max_amount' => 2000000,
                'requires_collateral' => false,
                'requires_guarantor' => true,
                'is_active' => true,
            ],
            [
                'code' => 'SAL-12',
                'name' => 'Salary Advance',
                'name_sw' => 'Mkopo wa Nivushe',
                'description' => 'Payroll-deducted loan for salaried employees up to 12 months.',
                'category' => 'salary_loan',
                'interest_rate' => 0.0350,
                'tenure_min_months' => 1,
                'tenure_max_months' => 12,
                'min_amount' => 200000,
                'max_amount' => 5000000,
                'requires_collateral' => false,
                'requires_guarantor' => false,
                'is_active' => true,
            ],
            [
                'code' => 'AGR-24',
                'name' => 'Agri-Cycle 24',
                'description' => 'Seasonal loan for farmers, repaid after harvest. Up to 24 months tenure.',
                'category' => 'agriculture',
                'interest_rate' => 0.0400,
                'tenure_min_months' => 6,
                'tenure_max_months' => 24,
                'min_amount' => 300000,
                'max_amount' => 10000000,
                'requires_collateral' => true,
                'requires_guarantor' => true,
                'is_active' => true,
            ],
            [
                'code' => 'AST-36',
                'name' => 'Asset Finance 36',
                'description' => 'Equipment & vehicle financing with the asset itself as collateral.',
                'category' => 'asset_finance',
                'interest_rate' => 0.0300,
                'tenure_min_months' => 12,
                'tenure_max_months' => 36,
                'min_amount' => 1000000,
                'max_amount' => 50000000,
                'requires_collateral' => true,
                'requires_guarantor' => false,
                'is_active' => true,
            ],
            [
                'code' => 'EMG-06',
                'name' => 'Emergency 6',
                'description' => 'Quick emergency loan, 1–6 months, smaller amounts.',
                'category' => 'emergency',
                'interest_rate' => 0.0600,
                'tenure_min_months' => 1,
                'tenure_max_months' => 6,
                'min_amount' => 50000,
                'max_amount' => 1000000,
                'requires_collateral' => false,
                'requires_guarantor' => true,
                'is_active' => true,
            ],
        ];

        foreach ($products as $row) {
            $category = strtolower((string) ($row['category'] ?? ''));
            $row['uses_capital_partner'] = ! in_array($category, ['asset_finance', 'asset_lending'], true)
                && ($row['code'] ?? '') !== 'AST-36';
            LoanProduct::query()->updateOrCreate(['code' => $row['code']], $row);
        }
    }
}
