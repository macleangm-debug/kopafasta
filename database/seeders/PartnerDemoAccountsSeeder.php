<?php

namespace Database\Seeders;

use App\Models\Lender;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateService;
use App\Services\PinService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Loginable demo accounts for every partner portal (local + production QA).
 *
 * Phone + PIN login (partner portal): use phone and PIN 1234 + partner code.
 * Email + password also works: Password@123
 */
class PartnerDemoAccountsSeeder extends Seeder
{
    public const PASSWORD = 'Password@123';

    public const PIN = '1234';

    public function run(): void
    {
        $partners = [
            [
                'email' => 'collection@kopafasta.local',
                'phone' => '+255710000101',
                'name' => 'Demo Collections Ltd',
                'category' => 'debt_collector',
                'code' => 'PTR-TEST-COL',
            ],
            [
                'email' => 'valuer@kopafasta.local',
                'phone' => '+255710000102',
                'name' => 'Demo Valuers Ltd',
                'category' => 'valuer',
                'code' => 'PTR-TEST-VAL',
            ],
            [
                'email' => 'gps@kopafasta.local',
                'phone' => '+255710000103',
                'name' => 'Demo GPS Installers',
                'category' => 'gps_installer',
                'code' => 'PTR-TEST-GPS',
            ],
            [
                'email' => 'insurance@kopafasta.local',
                'phone' => '+255710000104',
                'name' => 'Demo Insurance TZ',
                'category' => 'insurance',
                'code' => 'PTR-TEST-INS',
            ],
            [
                'email' => 'yard@kopafasta.local',
                'phone' => '+255710000105',
                'name' => 'Demo Yard Services',
                'category' => 'yard',
                'code' => 'PTR-TEST-YRD',
            ],
            [
                'email' => 'affiliate@kopafasta.local',
                'phone' => '+255710000106',
                'name' => 'Demo Affiliate Partner',
                'category' => 'affiliate',
                'code' => 'PTR-TEST-AFF',
                'affiliate_code' => 'TESTAFF01',
            ],
            [
                'email' => 'supplier@kopafasta.local',
                'phone' => '+255710000107',
                'name' => 'Demo Asset Supplier',
                'category' => 'supplier',
                'code' => 'PTR-TEST-SUP',
            ],
            [
                'email' => 'callcenter@kopafasta.local',
                'phone' => '+255710000108',
                'name' => 'Demo Call Center',
                'category' => 'call_center',
                'code' => 'PTR-TEST-CC',
            ],
        ];

        foreach ($partners as $row) {
            $this->seedPartner($row);
        }

        $this->seedCapitalPartner();
    }

    /** @param array<string, mixed> $row */
    private function seedPartner(array $row): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => $row['email']],
            [
                'name' => $row['name'],
                'phone' => $row['phone'],
                'password' => Hash::make(self::PASSWORD),
                'role' => 'vendor',
                'is_active' => true,
            ]
        );

        app(PinService::class)->setPin($user, self::PIN);

        $vendor = Vendor::query()->updateOrCreate(
            ['partner_number' => $row['code']],
            [
                'user_id' => $user->id,
                'name' => $row['name'],
                'legal_name' => $row['name'],
                'category' => $row['category'],
                'roles' => [$row['category']],
                'status' => 'active',
                'phone' => $row['phone'],
                'email' => $row['email'],
                'address' => 'Dar es Salaam, Tanzania',
                'regions' => ['Dar es Salaam'],
                'coverage_type' => 'regions',
                'activated_at' => now(),
                'registration_number' => 'BRELA-DEMO-'.substr($row['code'], -3),
                'tin' => '100-DEMO-'.substr($row['code'], -3),
            ]
        );

        if ($row['category'] === 'affiliate') {
            $vendor->update([
                'affiliate_code' => $row['affiliate_code'] ?? 'TESTAFF01',
                'affiliate_kyc_status' => 'verified',
                'affiliate_lifecycle_status' => 'active',
            ]);
            app(AffiliateService::class)->ensureCode($vendor->fresh());
        }
    }

    private function seedCapitalPartner(): void
    {
        $email = 'capital@kopafasta.local';
        $phone = '+255710000109';

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Demo Capital Partner',
                'phone' => $phone,
                'password' => Hash::make(self::PASSWORD),
                'role' => 'investor',
                'is_active' => true,
            ]
        );

        app(PinService::class)->setPin($user, self::PIN);

        Lender::query()->updateOrCreate(
            ['code' => 'DEMO-CAP'],
            [
                'user_id' => $user->id,
                'name' => 'Demo Capital Partners Ltd',
                'type' => 'institutional',
                'status' => 'active',
                'credit_limit' => 10_000_000,
                'available_balance' => 10_000_000,
                'auto_invest' => false,
            ]
        );
    }
}
