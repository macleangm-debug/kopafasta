<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vendor;
use App\Services\PinService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAffiliateSeeder extends Seeder
{
    public function run(): void
    {
        $phone = '+255712345678';
        $email = 'affiliate.demo@kopafasta.local';

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name'      => 'Demo Affiliate',
                'phone'     => $phone,
                'password'  => Hash::make('password'),
                'role'      => 'vendor',
                'is_active' => true,
            ]
        );

        app(PinService::class)->setPin($user, '1234');

        Vendor::query()->updateOrCreate(
            ['partner_number' => 'AFF-DEMO-001'],
            [
                'user_id'        => $user->id,
                'name'           => 'Demo Affiliate Partner',
                'category'       => 'affiliate',
                'status'         => 'active',
                'phone'          => $phone,
                'email'          => $email,
                'affiliate_code' => 'DEMOWAKALA',
                'activated_at'   => now(),
                'address'        => 'Dar es Salaam, Tanzania',
            ]
        );
    }
}
