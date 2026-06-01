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
            ['code' => 'COL', 'name' => 'Collections'],
            ['code' => 'CMP', 'name' => 'Compliance'],
            ['code' => 'FIN', 'name' => 'Finance'],
            ['code' => 'CS',  'name' => 'Customer Support'],
            ['code' => 'IT',  'name' => 'IT'],
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
