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
        $branches = [
            [
                'code' => 'HQ001',
                'name' => 'Head Office',
                'region' => 'Central',
                'phone' => '+255700000001',
                'email' => 'hq@kopafasta.local',
                'address' => 'Main Street, City Center',
                'is_active' => true,
            ],
            [
                'code' => 'BR-ILA',
                'name' => 'Ilala Branch',
                'region' => 'Dar es Salaam',
                'phone' => '+255700000002',
                'email' => 'ilala@kopafasta.local',
                'address' => 'Buguruni, Ilala District, Dar es Salaam',
                'is_active' => true,
            ],
            [
                'code' => 'BR-KIN',
                'name' => 'Kinondoni Branch',
                'region' => 'Dar es Salaam',
                'phone' => '+255700000003',
                'email' => 'kinondoni@kopafasta.local',
                'address' => 'Mwenge, Kinondoni District, Dar es Salaam',
                'is_active' => true,
            ],
            [
                'code' => 'BR-TEG',
                'name' => 'Tegeta Branch',
                'region' => 'Dar es Salaam',
                'phone' => '+255700000004',
                'email' => 'tegeta@kopafasta.local',
                'address' => 'Tegeta, Kinondoni District, Dar es Salaam',
                'is_active' => true,
            ],
            [
                'code' => 'BR003',
                'name' => 'Arusha Branch',
                'region' => 'Arusha',
                'phone' => '+255700000005',
                'email' => 'arusha@kopafasta.local',
                'address' => 'Clock Tower Area, Arusha',
                'is_active' => true,
            ],
        ];

        foreach ($branches as $branch) {
            Branch::query()->updateOrCreate(
                ['code' => $branch['code']],
                $branch
            );
        }

        // Digital-first: only Head Office is active for borrower-facing operations.
        Branch::query()->where('code', '!=', 'HQ001')->update(['is_active' => false]);
    }
}
