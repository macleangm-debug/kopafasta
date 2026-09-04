<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Safe configuration seeder.
 *
 * Creates or updates missing configuration without wiping business records.
 * Safe for staging and production deploys. Never truncates customer, loan,
 * payment, or partner data.
 *
 * Demo/test seeders must not be called from here.
 */
class SafeConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FinanceDefaultsSeeder::class,
            LoanPolicyDefaultsSeeder::class,
            RecoveryPolicyDefaultsSeeder::class,
            DepartmentSeeder::class,
            AuthPortalDefaultsSeeder::class,
            RoleSeeder::class,
            DefaultChartOfAccountsSeeder::class,
            DefaultWriteOffRulesSeeder::class,
            BranchSeeder::class,
            PublicLoanProductsSeeder::class,
            LoanProductRateTierSeeder::class,
            LoanProductPenaltyDefaultsSeeder::class,
            ChargesFeeSeeder::class,
            LoanProductPostApprovalFeeCatalogSeeder::class,
            NotificationTemplateSeeder::class,
            PlusLearningSeeder::class,
            KycDocumentTypeSeeder::class,
        ]);
    }
}
