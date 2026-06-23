<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

class PublicLoanProductsSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['code' => 'IL', 'name' => 'Individual Loan',   'category' => 'individual',  'interest_rate' => 0.19,  'min_amount' => 100000,  'max_amount' => 50000000, 'tenure_min_months' => 1, 'tenure_max_months' => 36, 'description' => 'Fast personal capital for any verified individual. No collateral for small tiers.'],
            ['code' => 'GL', 'name' => 'Group Loan',        'category' => 'group',       'interest_rate' => 0.18,  'min_amount' => 400_000,   'max_amount' => 10000000, 'tenure_min_months' => 3, 'tenure_max_months' => 12, 'repayment_cadence' => 'monthly', 'application_fee_amount' => 10_000, 'description' => 'Borrow together with shared liability. Best for chamas and savings circles.'],
            ['code' => 'AL', 'name' => 'Asset Lending',     'category' => 'asset',       'interest_rate' => 0.155, 'min_amount' => 500000,  'max_amount' => 100000000,'tenure_min_months' => 3, 'tenure_max_months' => 60, 'description' => 'Own the asset over time. Pay monthly. Title transfers when fully paid.'],
            ['code' => 'FC', 'name' => 'Artisans & Craftsmen Loan', 'category' => 'business', 'interest_rate' => 0.17,  'min_amount' => 50000,   'max_amount' => 5000000,  'tenure_min_months' => 1, 'tenure_max_months' => 12, 'description' => 'Funding capital for artisans, fundis and skilled tradespeople.'],
            ['code' => 'KB', 'name' => 'Kilimo Boost',      'category' => 'agriculture', 'interest_rate' => 0.155, 'min_amount' => 100000,  'max_amount' => 10000000, 'tenure_min_months' => 3, 'tenure_max_months' => 18, 'description' => 'Aligned to farming seasons. Grace periods supported.'],
            ['code' => 'BP', 'name' => 'Biashara Plus',     'category' => 'business',    'interest_rate' => 0.185, 'min_amount' => 500000,  'max_amount' => 50000000, 'tenure_min_months' => 3, 'tenure_max_months' => 36, 'description' => 'Scale-up capital for registered businesses with cashflow history.'],
            ['code' => 'EL', 'name' => 'Education Loan',    'category' => 'education',   'interest_rate' => 0.16,  'min_amount' => 50000,   'max_amount' => 15000000, 'tenure_min_months' => 1, 'tenure_max_months' => 24, 'description' => 'For tuition, books and education pathways. Term-aligned repayments.'],
            ['code' => 'EM', 'name' => 'Emergency Loan',    'category' => 'individual',  'interest_rate' => 0.20,  'min_amount' => 50000,   'max_amount' => 3000000,  'tenure_min_months' => 1, 'tenure_max_months' => 6,  'description' => 'When it cannot wait. Disbursed in hours after KYC clears.'],
            ['code' => 'WL', 'name' => 'Women Loan',        'category' => 'individual',  'interest_rate' => 0.165, 'min_amount' => 50000,   'max_amount' => 10000000, 'tenure_min_months' => 1, 'tenure_max_months' => 18, 'description' => 'Empowerment capital specifically for women-owned ventures.'],
            ['code' => 'AB', 'name' => 'Asset-Backed Loan', 'category' => 'asset',       'interest_rate' => 0.15,  'min_amount' => 500000,  'max_amount' => 100000000,'tenure_min_months' => 3, 'tenure_max_months' => 60, 'description' => 'Use a vehicle, machine or property as security to unlock larger capital at the best rates.'],
        ];

        foreach ($products as $p) {
            LoanProduct::updateOrCreate(
                ['code' => $p['code']],
                array_merge($p, [
                    'requires_collateral' => in_array($p['code'], ['AB', 'AL']),
                    'requires_guarantor'  => $p['code'] !== 'GL',
                    'is_active'           => true,
                ])
            );
        }
    }
}
