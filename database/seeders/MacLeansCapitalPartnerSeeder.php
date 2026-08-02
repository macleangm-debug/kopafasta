<?php

namespace Database\Seeders;

use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\LenderTransaction;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MacLeansCapitalPartnerSeeder extends Seeder
{
    public const PASSWORD = 'Password@123';

    public const PIN = '1234';

    public function run(): void
    {
        $capital = 50_000_000;

        $user = User::query()->updateOrCreate(
            ['email' => 'macleans@kopafasta.local'],
            [
                'name' => 'MacLeans Capital',
                'phone' => '+255710000209',
                'password' => Hash::make(self::PASSWORD),
                'role' => 'investor',
                'is_active' => true,
            ]
        );

        // Always re-assert credentials so demo login stays reliable after prior seeds.
        $user->forceFill([
            'name' => 'MacLeans Capital',
            'phone' => '+255710000209',
            'password' => Hash::make(self::PASSWORD),
            'role' => 'investor',
            'is_active' => true,
        ])->save();

        app(PinService::class)->setPin($user, self::PIN);

        $lender = Lender::updateOrCreate(
            ['code' => 'MACLEANS'],
            [
                'user_id'           => $user->id,
                'name'              => 'MacLeans Group of Companies',
                'type'              => 'institutional',
                'status'            => 'active',
                'credit_limit'      => $capital,
                'available_balance' => $capital,
                'auto_invest'       => true,
            ]
        );

        $pool = FundingPool::updateOrCreate(
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

        LenderTransaction::firstOrCreate(
            ['reference' => 'TXN-MACLEANS-INITIAL'],
            [
                'lender_id'       => $lender->id,
                'funding_pool_id' => $pool->id,
                'type'            => 'deposit',
                'direction'       => 'credit',
                'amount'          => $capital,
                'status'          => 'completed',
                'channel'         => 'system',
                'notes'           => 'Initial capital contribution — MacLeans Group of Companies',
                'processed_at'    => now(),
            ]
        );
    }
}
