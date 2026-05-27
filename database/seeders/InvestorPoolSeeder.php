<?php

namespace Database\Seeders;

use App\Models\FundingPool;
use App\Models\Lender;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InvestorPoolSeeder extends Seeder
{
    public function run(): void
    {
        // Create a "house" lender that owns the public pools.
        $house = Lender::firstOrCreate(
            ['code' => 'HOUSE'],
            [
                'name'              => 'Kopafasta House Pool',
                'type'              => 'institution',
                'status'            => 'active',
                'credit_limit'      => 0,
                'available_balance' => 0,
                'risk_preference'   => 'medium',
            ]
        );

        $pools = [
            [
                'name'             => 'Salary Loans Pool',
                'pool_type'        => 'salary',
                'risk_level'       => 'low',
                'expected_yield'   => 12.0,
                'amount_committed' => 200_000_000,
                'amount_deployed'  => 80_000_000,
                'active_borrowers' => 145,
                'repayment_rate'   => 97.5,
                'default_rate'     => 1.8,
                'min_investment'   => 100_000,
                'description'      => 'Short-term salary advance loans to vetted employees of registered companies. Lowest default rate in the platform.',
            ],
            [
                'name'             => 'Business SME Pool',
                'pool_type'        => 'business',
                'risk_level'       => 'medium',
                'expected_yield'   => 18.0,
                'amount_committed' => 500_000_000,
                'amount_deployed'  => 320_000_000,
                'active_borrowers' => 87,
                'repayment_rate'   => 93.2,
                'default_rate'     => 4.5,
                'min_investment'   => 250_000,
                'description'      => 'Working capital and inventory loans to growing SMEs across Tanzania. Secured against business assets.',
            ],
            [
                'name'             => 'Car Loans Pool',
                'pool_type'        => 'car',
                'risk_level'       => 'medium',
                'expected_yield'   => 16.0,
                'amount_committed' => 350_000_000,
                'amount_deployed'  => 150_000_000,
                'active_borrowers' => 62,
                'repayment_rate'   => 95.0,
                'default_rate'     => 3.2,
                'min_investment'   => 200_000,
                'description'      => 'Vehicle-backed loans with GPS tracking and registration retention until full repayment.',
            ],
            [
                'name'             => 'Emergency Loans Pool',
                'pool_type'        => 'emergency',
                'risk_level'       => 'high',
                'expected_yield'   => 24.0,
                'amount_committed' => 100_000_000,
                'amount_deployed'  => 42_000_000,
                'active_borrowers' => 210,
                'repayment_rate'   => 88.0,
                'default_rate'     => 7.5,
                'min_investment'   => 50_000,
                'description'      => 'Short-tenor emergency advances. Highest yield but elevated default risk — recommended for risk-tolerant investors.',
            ],
        ];

        foreach ($pools as $p) {
            FundingPool::firstOrCreate(
                ['lender_id' => $house->id, 'name' => $p['name']],
                array_merge($p, [
                    'currency'   => 'TZS',
                    'status'     => 'open',
                    'is_public'  => true,
                    'start_date' => now()->subMonths(1)->toDateString(),
                    'end_date'   => now()->addMonths(6)->toDateString(),
                ])
            );
        }
    }
}
