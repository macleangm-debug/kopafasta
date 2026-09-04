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
    /**
     * Local / first-bootstrap seed. Includes demo users and sample loans.
     *
     * Production and staging deploys use SafeConfigurationSeeder instead.
     * Never run `db:fresh --seed` on production.
     */
    public function run(): void
    {
        $this->call([
            // Safe configuration
            BranchSeeder::class,
            DepartmentSeeder::class,
            RoleSeeder::class,
            LoanProductSeeder::class,
            MacLeansCapitalPartnerSeeder::class,
            PublicLoanProductsSeeder::class,
            LoanProductRateTierSeeder::class,
            LoanProductPenaltyDefaultsSeeder::class,
            LoanPolicyDefaultsSeeder::class,
            ChargesFeeSeeder::class,
            ValuationPricingDefaultsSeeder::class,
            NotificationTemplateSeeder::class,
            PlusLearningSeeder::class,
            PublicHolidaySeeder::class,
            // Demo / local only — do not run on production
            UserSeeder::class,
            CustomerSeeder::class,
            DemoLoanSeeder::class,
            VendorSeeder::class,
            DemoAffiliateSeeder::class,
            PartnerDemoAccountsSeeder::class,
        ]);
    }
}
