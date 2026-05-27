<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        $defaultBranchId = DB::table('branches')->min('id');

        if ($defaultBranchId) {
            DB::table('users')
                ->whereIn('role', ['manager', 'officer', 'collector', 'credit_analyst'])
                ->whereNull('branch_id')
                ->update(['branch_id' => $defaultBranchId]);

            DB::table('customers')
                ->whereNull('branch_id')
                ->update(['branch_id' => $defaultBranchId]);
        }

        DB::table('loan_applications')
            ->whereNull('branch_id')
            ->whereNotNull('customer_id')
            ->update([
                'branch_id' => DB::raw('(select customers.branch_id from customers where customers.id = loan_applications.customer_id)'),
            ]);

        // MySQL rejects these CHECK constraints when the same columns are used by
        // foreign keys with referential actions (e.g. ON DELETE SET NULL).
        // Keep integrity for MySQL at the application/service layer.

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_staff_branch CHECK (role NOT IN ('manager','officer','collector','credit_analyst') OR branch_id IS NOT NULL)");
            DB::statement('ALTER TABLE customers ADD CONSTRAINT chk_customers_branch CHECK (branch_id IS NOT NULL)');
            DB::statement('ALTER TABLE loan_applications ADD CONSTRAINT chk_loan_applications_branch CHECK (branch_id IS NOT NULL)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        // No MySQL CHECK constraints are added in up().

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT chk_users_staff_branch');
            DB::statement('ALTER TABLE customers DROP CONSTRAINT chk_customers_branch');
            DB::statement('ALTER TABLE loan_applications DROP CONSTRAINT chk_loan_applications_branch');
        }
    }
};
