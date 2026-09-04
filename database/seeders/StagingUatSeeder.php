<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sanitized staging UAT accounts only. Never run on production.
 */
class StagingUatSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! app()->environment('staging')) {
            $this->command?->warn('StagingUatSeeder skipped: not a staging environment.');

            return;
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'uat.admin@staging.kopafasta.com'],
            [
                'name' => 'Staging Admin',
                'phone' => '255700000001',
                'role' => 'admin',
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($admin, '1234');

        $borrowerUser = User::query()->updateOrCreate(
            ['email' => 'uat.borrower@staging.kopafasta.com'],
            [
                'name' => 'UAT Borrower',
                'phone' => '255700000002',
                'role' => 'borrower',
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($borrowerUser, '1234');

        Customer::query()->updateOrCreate(
            ['customer_number' => 'CU-UAT-0001'],
            [
                'user_id' => $borrowerUser->id,
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'UAT',
                'last_name' => 'Borrower',
                'phone' => '255700000002',
                'country_code' => 'TZ',
                'loyalty_points' => 500,
                'membership_status' => 'active',
                'membership_expires_at' => now()->addYear(),
                'nida_verification_status' => 'verified',
                'face_verification_status' => 'verified',
                'date_of_birth' => now()->subYears(30)->toDateString(),
                'region' => 'Dar es Salaam',
                'district' => 'Kinondoni',
            ]
        );
    }
}
