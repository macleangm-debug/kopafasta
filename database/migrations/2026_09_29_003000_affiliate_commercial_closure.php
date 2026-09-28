<?php

use App\Models\ChartOfAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_payments') && ! Schema::hasColumn('partner_payments', 'meta')) {
            Schema::table('partner_payments', function (Blueprint $table): void {
                $table->json('meta')->nullable()->after('notes');
            });
        }

        if (Schema::hasTable('chart_of_accounts')) {
            foreach ([
                ['code' => '2140', 'name' => 'Affiliate Commission Payable', 'type' => 'liability'],
                ['code' => '5130', 'name' => 'Affiliate Commission Expense', 'type' => 'expense'],
            ] as $row) {
                ChartOfAccount::firstOrCreate(
                    ['code' => $row['code']],
                    ['name' => $row['name'], 'type' => $row['type'], 'is_active' => true],
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('partner_payments') && Schema::hasColumn('partner_payments', 'meta')) {
            Schema::table('partner_payments', function (Blueprint $table): void {
                $table->dropColumn('meta');
            });
        }
    }
};
