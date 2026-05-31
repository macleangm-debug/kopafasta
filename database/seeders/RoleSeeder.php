<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $all = array_keys(config('permissions.permissions', []));

        $roles = [
            [
                'code'        => 'officer',
                'name'        => 'Loan officer',
                'description' => 'Receives applications, acknowledges, and performs initial screening.',
                'permissions' => config('permissions.defaults.officer', []),
                'is_system'   => true,
            ],
            [
                'code'        => 'manager',
                'name'        => 'Credit manager',
                'description' => 'Reviews credit, pre-approves, approves, and rejects within approval limits.',
                'permissions' => config('permissions.defaults.manager', []),
                'is_system'   => true,
            ],
            [
                'code'        => 'credit_analyst',
                'name'        => 'Credit analyst',
                'description' => 'Underwriting and credit appraisal only.',
                'permissions' => config('permissions.defaults.credit_analyst', []),
                'is_system'   => true,
            ],
            [
                'code'        => 'admin',
                'name'        => 'Administrator',
                'description' => 'Full system access.',
                'permissions' => $all,
                'is_system'   => true,
            ],
            [
                'code'        => 'super_admin',
                'name'        => 'Super administrator',
                'description' => 'Full system access.',
                'permissions' => $all,
                'is_system'   => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['code' => $role['code']],
                $role,
            );
        }
    }
}
