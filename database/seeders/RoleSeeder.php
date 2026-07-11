<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /** @var array<string, string> */
    private const DESCRIPTIONS = [
        'admin'          => 'Full system access with hardcoded policy and permission bypass.',
        'super_admin'    => 'Console access with full permissions from roles table; subject to branch policies.',
        'manager'        => 'Reviews credit, pre-approves, approves, and rejects within approval limits.',
        'officer'        => 'Views and edits applications in the console; cannot advance workflow stages.',
        'credit_analyst' => 'Underwriting and credit appraisal in the admin console (recommendations before committee).',
        'credit_committee' => 'Credit committee reviewers who pre-approve and issue offers after analyst recommendation.',
        'collector'      => 'API-only repayments and arrears management.',
        'agent'          => 'Support tickets only; no console login.',
        'auditor'        => 'Read-only audit log access via API.',
        'borrower'       => 'Borrower portal self-service account.',
        'customer'       => 'Borrower portal account (legacy customer code).',
        'vendor'         => 'Partner portal account.',
        'investor'       => 'Investor / capital partner portal account.',
        'asset_manager'  => 'Manages asset lending marketplace listings and borrower asset requests.',
    ];

    public function run(): void
    {
        $allPermissions = array_keys(config('permissions.permissions', []));
        $defaults = config('permissions.defaults', []);
        $definitions = config('roles.definitions', []);

        foreach ($definitions as $code => $definition) {
            $permissions = match ($code) {
                'admin', 'super_admin' => $allPermissions,
                default => $defaults[$code] ?? [],
            };

            Role::updateOrCreate(
                ['code' => $code],
                [
                    'name'        => $definition['label'] ?? ucfirst(str_replace('_', ' ', $code)),
                    'description' => self::DESCRIPTIONS[$code] ?? null,
                    'permissions' => array_values(array_unique($permissions)),
                    'is_system'   => true,
                ],
            );
        }
    }
}
