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
            ['code' => 'CS',  'name' => 'Customer Support'],
            ['code' => 'UND', 'name' => 'Underwriting'],
            ['code' => 'COL', 'name' => 'Collections'],
            ['code' => 'FIN', 'name' => 'Finance'],
            ['code' => 'OPS', 'name' => 'Operations'],
            ['code' => 'MGT', 'name' => 'Management'],
            ['code' => 'MKT', 'name' => 'Marketing'],
            ['code' => 'REC', 'name' => 'Recovery'],
            ['code' => 'SYS', 'name' => 'System Administration'],
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
