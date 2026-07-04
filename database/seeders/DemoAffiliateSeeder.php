<?php

namespace Database\Seeders;

use App\Models\PartnerApplication;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateService;
use App\Services\PinService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAffiliateSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'affiliate.demo@kopafasta.local'],
            [
                'name'      => 'Demo Wakala',
                'phone'     => '+255712345678',
                'password'  => Hash::make('demo-affiliate-secret'),
                'role'      => 'vendor',
                'is_active' => true,
            ]
        );

        app(PinService::class)->setPin($user, '1234');

        $vendor = Vendor::query()->updateOrCreate(
            ['partner_number' => 'AFF-DEMO-001'],
            [
                'name'                  => 'Demo Wakala',
                'category'              => 'affiliate',
                'status'                => 'active',
                'phone'                 => '+255712345678',
                'email'                 => 'affiliate.demo@kopafasta.local',
                'address'               => 'Dar es Salaam, Tanzania',
                'user_id'               => $user->id,
                'activated_at'          => now(),
                'affiliate_code'        => 'DEMOWAKALA',
                'affiliate_kyc_status'  => 'verified',
                'registration_discount_percent' => 10,
                'application_discount_percent'  => 10,
            ]
        );

        app(AffiliateService::class)->ensureCode($vendor);

        PartnerApplication::query()->updateOrCreate(
            ['email' => 'affiliate.demo@kopafasta.local', 'type' => 'affiliate'],
            [
                'applicant_category' => 'individual',
                'full_name'          => 'Demo Wakala',
                'phone'              => '+255712345678',
                'business_name'      => null,
                'region'             => 'Dar es Salaam',
                'message'            => 'Demo affiliate account for end-to-end testing.',
                'status'             => 'approved',
                'reviewed_at'        => now(),
            ]
        );
    }
}
