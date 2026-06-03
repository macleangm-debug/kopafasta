<?php

namespace Database\Seeders;

use App\Models\FundingPool;
use App\Models\Lender;
use Illuminate\Database\Seeder;

class MacLeansCapitalPartnerSeeder extends Seeder
{
    public function run(): void
    {
        $capital = 50_000_000;

        $lender = Lender::updateOrCreate(
            ['code' => 'MACLEANS'],
            [
                'name'              => 'MacLeans Group of Companies',
                'type'              => 'institutional',
                'status'            => 'active',
                'credit_limit'      => $capital,
                'available_balance' => $capital,
                'auto_invest'       => true,
            ]
        );

        FundingPool::updateOrCreate(
            ['lender_id' => $lender->id, 'name' => 'MacLeans Primary Pool'],
            [
                'currency'          => 'TZS',
                'amount_committed'  => $capital,
                'amount_deployed'   => 0,
                'expected_yield'    => 15.0,
                'pool_type'         => 'business',
                'risk_level'        => 'medium',
                'status'            => 'open',
                'is_public'         => false,
                'description'       => 'Primary capital pool for MacLeans Group of Companies — TZS 50,000,000.',
                'start_date'        => now()->toDateString(),
            ]
        );
    }
}
