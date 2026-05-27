<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed default users for each core role.
     */
    public function run(): void
    {
        $hqBranch = Branch::query()->where('code', 'HQ001')->first();
        $darBranch = Branch::query()->where('code', 'BR002')->first();

        $users = [
            [
                'name' => 'System Admin',
                'email' => 'admin@kopafasta.local',
                'phone' => '+255700100001',
                'role' => 'admin',
                'branch_id' => $hqBranch?->id,
                'approval_limit' => 100000000,
                'is_active' => true,
            ],
            [
                'name' => 'Branch Manager',
                'email' => 'manager@kopafasta.local',
                'phone' => '+255700100002',
                'role' => 'manager',
                'branch_id' => $darBranch?->id,
                'approval_limit' => 50000000,
                'is_active' => true,
            ],
            [
                'name' => 'Credit Officer',
                'email' => 'officer@kopafasta.local',
                'phone' => '+255700100003',
                'role' => 'officer',
                'branch_id' => $darBranch?->id,
                'approval_limit' => 10000000,
                'is_active' => true,
            ],
            [
                'name' => 'Collections Officer',
                'email' => 'collector@kopafasta.local',
                'phone' => '+255700100004',
                'role' => 'collector',
                'branch_id' => $darBranch?->id,
                'approval_limit' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Sample Customer',
                'email' => 'customer@kopafasta.local',
                'phone' => '+255700100005',
                'role' => 'customer',
                'branch_id' => $darBranch?->id,
                'approval_limit' => null,
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    ...$userData,
                    'password' => Hash::make('Password@123'),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
