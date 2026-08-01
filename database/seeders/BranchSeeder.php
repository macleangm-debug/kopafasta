<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Seed the application's database with default branches.
     */
    public function run(): void
    {
        Branch::query()->updateOrCreate(
            ['code' => 'HQ001'],
            [
                'name' => 'Head Office',
                'region' => 'Central',
                'phone' => '+255700000001',
                'email' => 'hq@kopafasta.local',
                'address' => 'Main Street, City Center',
                'is_active' => true,
            ]
        );

        // Digital-first: only Head Office is active for borrower-facing operations.
        Branch::query()->where('code', '!=', 'HQ001')->update(['is_active' => false]);
    }
}
