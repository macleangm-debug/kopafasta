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
                'code' => 'BR002',
                'name' => 'Dar Branch',
                'region' => 'Dar es Salaam',
                'phone' => '+255700000002',
                'email' => 'dar@kopafasta.local',
                'address' => 'Kariakoo, Dar es Salaam',
                'is_active' => true,
            ],
            [
                'code' => 'BR003',
                'name' => 'Arusha Branch',
                'region' => 'Arusha',
                'phone' => '+255700000003',
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
    }
}
