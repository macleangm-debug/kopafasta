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
                'description' => 'Views and edits applications in the console; cannot advance workflow stages.',
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
                'description' => 'API-only underwriting and credit appraisal.',
                'permissions' => config('permissions.defaults.credit_analyst', []),
                'is_system'   => true,
            ],
            [
                'code'        => 'collector',
                'name'        => 'Collector',
                'description' => 'API-only repayments and arrears management.',
                'permissions' => config('permissions.defaults.collector', []),
                'is_system'   => true,
            ],
            [
                'code'        => 'agent',
                'name'        => 'Support agent',
                'description' => 'Support tickets only; no console login.',
                'permissions' => config('permissions.defaults.agent', []),
                'is_system'   => true,
            ],
            [
                'code'        => 'auditor',
                'name'        => 'Auditor',
                'description' => 'Read-only audit log access via API.',
                'permissions' => config('permissions.defaults.auditor', []),
                'is_system'   => true,
            ],
            [
                'code'        => 'admin',
                'name'        => 'Administrator',
                'description' => 'Full system access with hardcoded policy and permission bypass.',
                'permissions' => $all,
                'is_system'   => true,
            ],
            [
                'code'        => 'super_admin',
                'name'        => 'Super administrator',
                'description' => 'Console access with full permissions from roles table; subject to branch policies.',
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
