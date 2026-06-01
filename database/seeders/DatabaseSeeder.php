<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            DepartmentSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            LoanProductSeeder::class,
            PublicLoanProductsSeeder::class,
            CustomerSeeder::class,
            DemoLoanSeeder::class,
            VendorSeeder::class,
            ChargesFeeSeeder::class,
            NotificationTemplateSeeder::class,
        ]);
    }
}
