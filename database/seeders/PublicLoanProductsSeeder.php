<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

class PublicLoanProductsSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['code' => 'IL', 'name' => 'Individual Loan',     'category' => 'individual', 'interest_rate' => 0.19,  'min_amount' => 100000,  'max_amount' => 50000000, 'tenure_min_months' => 1, 'tenure_max_months' => 36, 'description' => 'Fast personal capital for any verified individual. No collateral for small tiers.'],
            ['code' => 'GL', 'name' => 'Group Loan',          'category' => 'group',      'interest_rate' => 0.18,  'min_amount' => 50000,   'max_amount' => 10000000, 'tenure_min_months' => 1, 'tenure_max_months' => 12, 'description' => 'Borrow together with shared liability. Best for chamas and saccos.'],
            ['code' => 'AB', 'name' => 'Asset Backed',        'category' => 'asset',      'interest_rate' => 0.15,  'min_amount' => 500000,  'max_amount' => 100000000,'tenure_min_months' => 3, 'tenure_max_months' => 60, 'description' => 'Use a vehicle, machine or property as security to unlock larger capital at the best rates.'],
            ['code' => 'AL', 'name' => 'Asset Lending',       'category' => 'asset',      'interest_rate' => 0.155, 'min_amount' => 500000,  'max_amount' => 100000000,'tenure_min_months' => 3, 'tenure_max_months' => 60, 'description' => 'Own the asset over time. Pay monthly. Title transfers when fully paid.'],
            ['code' => 'AC', 'name' => 'Fundi Capital',       'category' => 'individual', 'interest_rate' => 0.17,  'min_amount' => 50000,   'max_amount' => 5000000,  'tenure_min_months' => 1, 'tenure_max_months' => 12, 'description' => 'Designed for artisans: mechanics, tailors, electricians and craftspeople.'],
            ['code' => 'AG', 'name' => 'Kilimo Boost',        'category' => 'agriculture','interest_rate' => 0.155, 'min_amount' => 100000,  'max_amount' => 10000000, 'tenure_min_months' => 3, 'tenure_max_months' => 18, 'description' => 'Aligned to farming seasons. Grace periods supported.'],
            ['code' => 'BZ', 'name' => 'Biashara Plus',       'category' => 'business',   'interest_rate' => 0.185, 'min_amount' => 500000,  'max_amount' => 50000000, 'tenure_min_months' => 3, 'tenure_max_months' => 36, 'description' => 'Scale-up capital for registered businesses with cashflow history.'],
            ['code' => 'ED', 'name' => 'Elimu Loan',          'category' => 'education',  'interest_rate' => 0.16,  'min_amount' => 50000,   'max_amount' => 15000000, 'tenure_min_months' => 1, 'tenure_max_months' => 24, 'description' => 'For tuition, books and education pathways. Term-aligned repayments.'],
            ['code' => 'EM', 'name' => 'Emergency',           'category' => 'individual', 'interest_rate' => 0.20,  'min_amount' => 50000,   'max_amount' => 3000000,  'tenure_min_months' => 1, 'tenure_max_months' => 6,  'description' => 'When it cannot wait. Disbursed in hours after KYC clears.'],
            ['code' => 'WM', 'name' => 'Wanawake',            'category' => 'individual', 'interest_rate' => 0.165, 'min_amount' => 50000,   'max_amount' => 10000000, 'tenure_min_months' => 1, 'tenure_max_months' => 18, 'description' => 'Empowerment capital specifically for women-owned ventures.'],
        ];

        foreach ($products as $p) {
            LoanProduct::updateOrCreate(
                ['code' => $p['code']],
                array_merge($p, [
                    'requires_collateral' => in_array($p['code'], ['AB', 'AL']),
                    'requires_guarantor'  => in_array($p['code'], ['GL', 'BZ']),
                    'is_active'           => true,
                ])
            );
        }
    }
}
