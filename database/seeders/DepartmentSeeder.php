<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = Branch::query()->where('code', 'HQ001')->value('id')
            ?? Branch::query()->where('is_active', true)->value('id');

        $departments = [
            ['code' => 'OPS', 'name' => 'Operations'],
            ['code' => 'CRD', 'name' => 'Credit'],
            ['code' => 'UND', 'name' => 'Underwriting'],
            ['code' => 'CRC', 'name' => 'Credit Committee'],
            ['code' => 'CRM', 'name' => 'Credit Management'],
            ['code' => 'COL', 'name' => 'Collections'],
            ['code' => 'CMP', 'name' => 'Compliance'],
            ['code' => 'FIN', 'name' => 'Finance'],
            ['code' => 'CS',  'name' => 'Customer Support'],
            ['code' => 'IT',  'name' => 'Information Technology'],
            ['code' => 'MGT', 'name' => 'Management'],
            ['code' => 'MKT', 'name' => 'Marketing'],
            ['code' => 'REC', 'name' => 'Recovery'],
            ['code' => 'SYS', 'name' => 'System Administration'],
            ['code' => 'PRT', 'name' => 'Partner Operations'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                [
                    'name'       => $department['name'],
                    'branch_id'  => $branchId,
                    'is_active'  => true,
                ]
            );
        }
    }
}
